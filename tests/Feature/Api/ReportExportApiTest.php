<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Iphones;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_export_sales_report_as_csv_file(): void
    {
        $now = Carbon::now('Asia/Jakarta');
        $phone = Iphones::factory()->create(['name' => 'iPhone 15 Pro']);
        $cash = Payment::create(['name' => 'Tunai Kasir', 'slug' => 'cash']);

        $booking = Booking::create([
            'booking_code' => 'SKY-CSV-01',
            'iphone_id' => $phone->id,
            'customer_name' => 'Rendra Pratama',
            'customer_phone' => '081234567800',
            'start_booking_date' => $now->toDateString(),
            'end_booking_date' => $now->copy()->addDays(2)->toDateString(),
            'duration' => 48,
            'price' => 500000,
            'deposit_amount' => 200000,
            'status' => 'rented',
            'created' => now(),
        ]);

        BookingPayment::create([
            'payment_code' => 'PAY-CSV-01',
            'booking_id' => $booking->id,
            'payment_id' => $cash->id,
            'amount' => 500000,
            'type' => 'rental',
            'paid_at' => $now,
        ]);

        $response = $this->get('/api/v1/reports/export/csv?period=Hari Ini');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.csv', $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringContainsString('Kode Booking', $content);
        $this->assertStringContainsString('SKY-CSV-01', $content);
        $this->assertStringContainsString('Rendra Pratama', $content);
        $this->assertStringContainsString('500000', $content);
        $this->assertStringContainsString('Total Omzet', $content);

        // Also test alias endpoint /api/v1/reports/sales/export
        $aliasResp = $this->get('/api/v1/reports/sales/export?period=Hari Ini');
        $aliasResp->assertStatus(200);
        $this->assertStringContainsString('text/csv', $aliasResp->headers->get('Content-Type'));
    }

    public function test_can_export_closing_report_as_json(): void
    {
        $now = Carbon::now('Asia/Jakarta');
        $phone = Iphones::factory()->create();
        $qris = Payment::create(['name' => 'QRIS', 'slug' => 'qris']);

        $booking = Booking::create([
            'booking_code' => 'SKY-CLOSE-01',
            'iphone_id' => $phone->id,
            'customer_name' => 'Dewi Sartika',
            'customer_phone' => '081234567801',
            'start_booking_date' => $now->toDateString(),
            'end_booking_date' => $now->copy()->addDay()->toDateString(),
            'duration' => 24,
            'price' => 300000,
            'status' => 'rented',
            'created' => now(),
        ]);

        BookingPayment::create([
            'payment_code' => 'PAY-CLOSE-01',
            'booking_id' => $booking->id,
            'payment_id' => $qris->id,
            'amount' => 300000,
            'type' => 'rental',
            'paid_at' => $now,
        ]);

        $response = $this->getJson('/api/v1/reports/export/closing?format=json');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'format' => 'esc_pos_58mm_32col',
            ])
            ->assertJsonStructure([
                'status',
                'format',
                'filename',
                'text',
                'summary',
                'paymentMethodBreakdown',
            ]);

        $this->assertStringContainsString('LAPORAN PENUTUPAN KASIR', $response->json('text'));
        $this->assertStringContainsString('300.000', $response->json('text'));
    }

    public function test_esc_pos_closing_text_does_not_exceed_32_characters_per_line(): void
    {
        $now = Carbon::now('Asia/Jakarta');
        $phone = Iphones::factory()->create();
        $cash = Payment::create(['name' => 'Tunai Kasir Super Panjang', 'slug' => 'cash']);

        $booking = Booking::create([
            'booking_code' => 'SKY-CLOSE-LEN',
            'iphone_id' => $phone->id,
            'customer_name' => 'Pelanggan Sangat Panjang Sekali Namanya',
            'customer_phone' => '081234567802',
            'start_booking_date' => $now->toDateString(),
            'end_booking_date' => $now->copy()->addDay()->toDateString(),
            'duration' => 24,
            'price' => 1250000,
            'status' => 'rented',
            'created' => now(),
        ]);

        BookingPayment::create([
            'payment_code' => 'PAY-CLOSE-LEN',
            'booking_id' => $booking->id,
            'payment_id' => $cash->id,
            'amount' => 1250000,
            'type' => 'rental',
            'paid_at' => $now,
        ]);

        $response = $this->get('/api/v1/reports/export/closing');

        $response->assertStatus(200);
        $text = $response->getContent();

        $lines = explode("\n", str_replace("\r", '', $text));
        foreach ($lines as $lineIdx => $line) {
            $this->assertLessThanOrEqual(
                32,
                strlen($line),
                "Line {$lineIdx} exceeds 32 characters: '{$line}' (length: " . strlen($line) . ")"
            );
        }
    }
}
