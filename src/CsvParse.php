<?php

namespace PixelTrack;

class CsvParse
{
    private array $processedData;

    public function __construct(string $filename)
    {
        $rawData = $this->parse($filename);

        $this->processedData = $this->process($rawData);
    }

    public function getProcessedData(): array
    {
        return $this->processedData;
    }

    public function getJsonProcessedData(): string
    {
        return json_encode($this->processedData);
    }

    private function parse(string $filename)
    {
        $dataRows = [];
        if (($handle = fopen("../data/" . $filename, "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, "\t")) !== false) {
                $dataRows[] = [
                    'position' => $data[0],
                    'name' => $data[1],
                    'team' => $data[2],
                    'id' => $data[3],
                    'time' => $data[4],
                    'diff' => $data[5],
                ];
            }
            fclose($handle);
        }

        return $dataRows;
    }

    private function process(array $rawData): array
    {
        $processedData = [];
        foreach ($rawData as $row) {
            $timeInMinutes = $this->getTimeInMinutes($row['time']);
            $diffTimeInMinutes = $this->getTimeInMinutes($row['diff']);

            $processedData[] = array_merge($row, [
                'timeInMinutes' => $timeInMinutes,
                'diffTimeInMinutes' => $diffTimeInMinutes,
            ]);
        }

        return $processedData;
    }

    private function getTimeInMinutes(string $time): int
    {
        [$hours, $minutes, $seconds] = sscanf($time, "%02d:%02d:%f");

        return $hours * 60 + $minutes;
    }
}
