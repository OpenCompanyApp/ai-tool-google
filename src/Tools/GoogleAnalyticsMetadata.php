<?php

namespace OpenCompany\AiToolGoogle\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\AiToolGoogle\Services\GoogleAnalyticsService;

class GoogleAnalyticsMetadata implements Tool
{
    public function __construct(
        private GoogleAnalyticsService $service,
    ) {}

    public function description(): string
    {
        return 'List all available dimensions and metrics for a GA4 property, including custom ones. Use this to discover what data can be queried in reports.';
    }

    public function handle(Request $request): string
    {
        try {
            if (! $this->service->isConfigured()) {
                return 'Error: Google Analytics integration is not configured.';
            }

            $propertyId = $request['propertyId'] ?? '';
            if (empty($propertyId)) {
                return 'Error: propertyId is required. Use google_analytics_list_properties to find your GA4 property ID.';
            }

            $result = $this->service->getMetadata((string) $propertyId);

            $dimensions = $result['dimensions'] ?? [];
            $metrics = $result['metrics'] ?? [];

            // Group dimensions by category
            $dimGroups = [];
            foreach ($dimensions as $dim) {
                $category = $dim['category'] ?? 'Other';
                $name = $dim['apiName'] ?? '';
                $desc = $dim['uiName'] ?? '';
                $custom = isset($dim['customDefinition']) && $dim['customDefinition'];
                $label = $custom ? "{$name} - {$desc} [custom]" : "{$name} - {$desc}";
                $dimGroups[$category][] = $label;
            }

            // Group metrics by category
            $metricGroups = [];
            foreach ($metrics as $metric) {
                $category = $metric['category'] ?? 'Other';
                $name = $metric['apiName'] ?? '';
                $desc = $metric['uiName'] ?? '';
                $custom = isset($metric['customDefinition']) && $metric['customDefinition'];
                $label = $custom ? "{$name} - {$desc} [custom]" : "{$name} - {$desc}";
                $metricGroups[$category][] = $label;
            }

            $lines = [];
            $lines[] = count($dimensions) . ' dimensions, ' . count($metrics) . " metrics available.\n";

            $lines[] = 'DIMENSIONS:';
            foreach ($dimGroups as $category => $items) {
                $lines[] = "\n  {$category}:";
                foreach ($items as $item) {
                    $lines[] = "    {$item}";
                }
            }

            $lines[] = "\nMETRICS:";
            foreach ($metricGroups as $category => $items) {
                $lines[] = "\n  {$category}:";
                foreach ($items as $item) {
                    $lines[] = "    {$item}";
                }
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'propertyId' => $schema
                ->string()
                ->description('GA4 property ID (numeric, e.g., "123456789"). Use google_analytics_list_properties to find it.')
                ->required(),
        ];
    }
}
