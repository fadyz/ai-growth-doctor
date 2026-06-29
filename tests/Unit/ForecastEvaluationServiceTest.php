<?php

namespace Tests\Unit;

use App\Services\GrowthDoctor\ForecastEvaluationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ForecastEvaluationServiceTest extends TestCase
{
    public function testSortEvaluationsNewestFirstUsesBusinessDatesInsteadOfInputOrder(): void
    {
        $service = new ForecastEvaluationService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('sortEvaluationsNewestFirst');
        $method->setAccessible(true);

        $sorted = $method->invoke($service, [
            [
                'forecast_for_date' => '2026-06-13',
                'data_as_of_date' => '2026-06-12',
                'created_at' => '2026-06-29 09:00:00',
                'summary' => [
                    'forecast_quality' => 'poor',
                ],
            ],
            [
                'forecast_for_date' => '2026-06-25',
                'data_as_of_date' => '2026-06-24',
                'created_at' => '2026-06-20 08:00:00',
                'summary' => [
                    'forecast_quality' => 'partially_correct',
                ],
            ],
        ]);

        $this->assertSame('2026-06-25', $sorted[0]['forecast_for_date']);
        $this->assertSame('partially_correct', $sorted[0]['summary']['forecast_quality']);
        $this->assertSame('2026-06-13', $sorted[1]['forecast_for_date']);
    }

    public function testSortEvaluationsNewestFirstUsesDataAsOfAndCreatedAtAsTieBreakers(): void
    {
        $service = new ForecastEvaluationService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('sortEvaluationsNewestFirst');
        $method->setAccessible(true);

        $sorted = $method->invoke($service, [
            [
                'forecast_for_date' => '2026-06-25',
                'data_as_of_date' => '2026-06-23',
                'created_at' => '2026-06-25 09:00:00',
            ],
            [
                'forecast_for_date' => '2026-06-25',
                'data_as_of_date' => '2026-06-24',
                'created_at' => '2026-06-24 09:00:00',
            ],
            [
                'forecast_for_date' => '2026-06-25',
                'data_as_of_date' => '2026-06-24',
                'created_at' => '2026-06-24 10:00:00',
            ],
        ]);

        $this->assertSame('2026-06-24', $sorted[0]['data_as_of_date']);
        $this->assertSame('2026-06-24 10:00:00', $sorted[0]['created_at']);
        $this->assertSame('2026-06-24 09:00:00', $sorted[1]['created_at']);
        $this->assertSame('2026-06-23', $sorted[2]['data_as_of_date']);
    }
}
