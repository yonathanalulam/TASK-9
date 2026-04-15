<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\ExportJob;

class CsvExportRenderer
{
    public function __construct(
        private readonly string $exportDir,
    ) {
    }

    /**
     * Render export data to a CSV file with watermark as first row comment.
     *
     * @param array<int, array<string, mixed>> $data
     * @return string The file path of the generated CSV.
     */
    public function render(ExportJob $job, array $data): string
    {
        if (!is_dir($this->exportDir)) {
            mkdir($this->exportDir, 0o755, true);
        }

        $filePath = $this->exportDir . '/' . $job->getId()->toRfc4122() . '.csv';
        $handle = fopen($filePath, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open file for writing: ' . $filePath);
        }

        try {
            // Write watermark as first row comment
            if ($job->getWatermarkText() !== null) {
                fwrite($handle, '# ' . $job->getWatermarkText() . "\n");
            }

            if (\count($data) === 0) {
                fwrite($handle, "# No data found for export\n");

                return $filePath;
            }

            // Write header row from first record's keys
            $headers = array_keys($data[0]);
            fputcsv($handle, $headers);

            // Write data rows
            foreach ($data as $row) {
                $values = array_map(static function (mixed $value): string {
                    if ($value === null) {
                        return '';
                    }

                    if ($value instanceof \DateTimeInterface) {
                        return $value->format('c');
                    }

                    if (\is_array($value)) {
                        return json_encode($value, \JSON_THROW_ON_ERROR);
                    }

                    if (\is_bool($value)) {
                        return $value ? '1' : '0';
                    }

                    return (string) $value;
                }, $row);

                fputcsv($handle, $values);
            }

            return $filePath;
        } finally {
            fclose($handle);
        }
    }
}
