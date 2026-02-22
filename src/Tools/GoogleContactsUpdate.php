<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleContactsService;

class GoogleContactsUpdate implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function description(): string
    {
        return 'Update an existing Google Contact. Unspecified fields are preserved. Email, phone, and address are added alongside existing values; name, company, title, and notes are replaced.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Contacts integration is not configured.';
            }

            $resourceName = $request['resourceName'] ?? '';
            if (empty($resourceName)) {
                return 'Error: resourceName is required.';
            }

            // Fetch current contact to get etag
            $current = $this->service->getContact($resourceName);
            $etag = $current['etag'] ?? '';
            if (empty($etag)) {
                return 'Error: Could not retrieve contact etag. Contact may not exist.';
            }

            $data = $this->mergeContactData($current, $request);
            $updateFields = $this->getUpdateFields($request);

            if (empty($updateFields)) {
                return 'Error: Provide at least one field to update (name, email, phone, company, title, address, notes).';
            }

            $result = $this->service->updateContact(
                $resourceName,
                $data,
                $etag,
                implode(',', $updateFields),
            );

            $contact = GoogleContactsService::formatContact($result);

            return json_encode(array_merge(
                ['message' => 'Contact updated.'],
                $contact,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function mergeContactData(array $current, Request $request): array
    {
        $data = [];

        // Name -- replace entirely (single-valued)
        $name = $request['name'] ?? '';
        if ($name !== '') {
            $parts = explode(' ', $name, 2);
            $data['names'] = [[
                'givenName' => $parts[0],
                'familyName' => $parts[1] ?? '',
            ]];
        }

        // Email -- append to existing (avoid duplicates)
        $email = $request['email'] ?? '';
        if ($email !== '') {
            $existing = $current['emailAddresses'] ?? [];
            $alreadyExists = false;
            foreach ($existing as $e) {
                if (strcasecmp($e['value'] ?? '', $email) === 0) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (! $alreadyExists) {
                $existing[] = ['value' => $email];
            }
            $data['emailAddresses'] = $existing;
        }

        // Phone -- append to existing (avoid duplicates)
        $phone = $request['phone'] ?? '';
        if ($phone !== '') {
            $existing = $current['phoneNumbers'] ?? [];
            $alreadyExists = false;
            foreach ($existing as $p) {
                if (($p['value'] ?? '') === $phone) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (! $alreadyExists) {
                $existing[] = ['value' => $phone];
            }
            $data['phoneNumbers'] = $existing;
        }

        // Organization -- replace (single-valued effectively)
        $company = $request['company'] ?? '';
        $title = $request['title'] ?? '';
        if ($company !== '' || $title !== '') {
            $org = [];
            if ($company !== '') {
                $org['name'] = $company;
            }
            if ($title !== '') {
                $org['title'] = $title;
            }
            $data['organizations'] = [$org];
        }

        // Address -- append to existing (avoid duplicates)
        $address = $request['address'] ?? '';
        if ($address !== '') {
            $existing = $current['addresses'] ?? [];
            $alreadyExists = false;
            foreach ($existing as $a) {
                if (($a['formattedValue'] ?? '') === $address) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (! $alreadyExists) {
                $existing[] = ['formattedValue' => $address];
            }
            $data['addresses'] = $existing;
        }

        // Notes -- replace (single-valued)
        $notes = $request['notes'] ?? '';
        if ($notes !== '') {
            $data['biographies'] = [['value' => $notes, 'contentType' => 'TEXT_PLAIN']];
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function getUpdateFields(Request $request): array
    {
        $fields = [];

        if (isset($request['name']) && $request['name'] !== '') {
            $fields[] = 'names';
        }
        if (isset($request['email']) && $request['email'] !== '') {
            $fields[] = 'emailAddresses';
        }
        if (isset($request['phone']) && $request['phone'] !== '') {
            $fields[] = 'phoneNumbers';
        }
        if ((isset($request['company']) && $request['company'] !== '') || (isset($request['title']) && $request['title'] !== '')) {
            $fields[] = 'organizations';
        }
        if (isset($request['address']) && $request['address'] !== '') {
            $fields[] = 'addresses';
        }
        if (isset($request['notes']) && $request['notes'] !== '') {
            $fields[] = 'biographies';
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resourceName' => $schema
                ->string()
                ->description('Contact resource name (e.g., "people/c1234567890").')
                ->required(),
            'name' => $schema
                ->string()
                ->description('Full name (e.g., "John Doe"). Replaces existing name.'),
            'email' => $schema
                ->string()
                ->description('Email address. Added alongside existing emails.'),
            'phone' => $schema
                ->string()
                ->description('Phone number (e.g., "+1-555-0123"). Added alongside existing phones.'),
            'company' => $schema
                ->string()
                ->description('Company/organization name.'),
            'title' => $schema
                ->string()
                ->description('Job title.'),
            'address' => $schema
                ->string()
                ->description('Full address (e.g., "123 Main St, Springfield, IL 62701").'),
            'notes' => $schema
                ->string()
                ->description('Notes or biography for the contact.'),
        ];
    }
}
