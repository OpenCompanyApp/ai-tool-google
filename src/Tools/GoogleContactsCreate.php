<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleContactsService;

class GoogleContactsCreate implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function description(): string
    {
        return 'Create a new Google Contact with name, email, phone, company, title, address, and notes.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Contacts integration is not configured.';
            }

            $name = $request['name'] ?? '';
            if (empty($name)) {
                return 'Error: name is required.';
            }

            $data = $this->buildContactData($request);

            $result = $this->service->createContact($data);
            $contact = GoogleContactsService::formatContact($result);

            return json_encode(array_merge(
                ['message' => "Contact '{$name}' created."],
                $contact,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContactData(Request $request): array
    {
        $data = [];

        $name = $request['name'] ?? '';
        if ($name !== '') {
            $parts = explode(' ', $name, 2);
            $data['names'] = [[
                'givenName' => $parts[0],
                'familyName' => $parts[1] ?? '',
            ]];
        }

        $email = $request['email'] ?? '';
        if ($email !== '') {
            $data['emailAddresses'] = [['value' => $email]];
        }

        $phone = $request['phone'] ?? '';
        if ($phone !== '') {
            $data['phoneNumbers'] = [['value' => $phone]];
        }

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

        $address = $request['address'] ?? '';
        if ($address !== '') {
            $data['addresses'] = [['formattedValue' => $address]];
        }

        $notes = $request['notes'] ?? '';
        if ($notes !== '') {
            $data['biographies'] = [['value' => $notes, 'contentType' => 'TEXT_PLAIN']];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->description('Full name (e.g., "John Doe").')
                ->required(),
            'email' => $schema
                ->string()
                ->description('Email address.'),
            'phone' => $schema
                ->string()
                ->description('Phone number (e.g., "+1-555-0123").'),
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
