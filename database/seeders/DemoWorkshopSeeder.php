<?php

namespace Database\Seeders;

use App\Models\ServiceInventory;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopKnowledgeItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Yoshida Automotive, as shown in the site design. Company facts come from that
 * design; the prices and capacities below are samples to replace with real ones.
 */
class DemoWorkshopSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@yoshida-automotive.test'],
            ['name' => 'Owner', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );

        $description = '板金・塗装、整備・車検、自動車保険、車販売、タイヤ販売・交換の総合サービス。';

        $workshop = Workshop::updateOrCreate(
            ['slug' => 'yoshida-automotive'],
            [
                'name' => 'Yoshida Automotive',
                'description' => $description,
                'translations' => [
                    'ja' => ['name' => '吉田自動車工業', 'description' => $description],
                    'en' => ['description' => 'Body and paint, maintenance and inspection, auto insurance, car sales, and tire sales and change in one place.'],
                    'id' => ['description' => 'Bodi dan cat, servis dan inspeksi, asuransi mobil, penjualan mobil, serta penjualan dan penggantian ban dalam satu tempat.'],
                ],
                'country' => 'Japan',
                'phone' => '042-556-1219',
                'timezone' => 'Asia/Tokyo',
                'currency' => 'JPY',
                'default_locale' => 'ja',
                'opening_time' => '09:00',
                'closing_time' => '18:00',
                'public_status' => 'published',
            ]
        );

        $workshop->users()->syncWithoutDetaching([$owner->id => ['role' => 'owner', 'status' => 'active']]);

        foreach ($this->serviceTypeDefinitions() as $sort => $definition) {
            $totalUnits = $definition['total_units'];
            unset($definition['total_units']);

            $serviceType = $workshop->serviceTypes()->updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'sort_order' => $sort, 'is_active' => true]
            );

            $this->seedInventory($serviceType, $totalUnits);
        }

        $this->seedKnowledgeBase($workshop);

        $this->command?->info('Demo login: owner@yoshida-automotive.test — password: password');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviceTypeDefinitions(): array
    {
        return [
            $this->service('scratch-repair', 'キズ・すりキズ修理', 'Scratch repair', 'Perbaikan baret', 'バンパーやドアのキズを修理・塗装します。', 'Repairs and repaints scratches on bumpers and doors.', 'Memperbaiki dan mengecat baret pada bemper dan pintu.', 20000, 240, ['computer_scan'], 3),
            $this->service('dent-repair', 'へこみ修理', 'Dent repair', 'Perbaikan penyok', 'ドアやフェンダーのへこみを修理・塗装します。', 'Repairs and repaints dents on doors and fenders.', 'Memperbaiki dan mengecat penyok pada pintu dan fender.', 30000, 480, [], 2),
            $this->service('oil-change', 'オイル交換', 'Oil change', 'Ganti oli', 'エンジンオイルとオイルフィルターを交換します。', 'Replaces the engine oil and oil filter.', 'Mengganti oli mesin dan filter oli.', 5000, 30, ['engine_oil', 'oil_filter'], 6),
            $this->service('vehicle-inspection', '車検（継続検査）', 'Statutory inspection', 'Inspeksi berkala (shaken)', '法定24ヶ月点検と各部の安全確認・整備を行います。', 'The statutory 24-month check with safety checks and servicing.', 'Pemeriksaan wajib 24 bulan dengan pengecekan keselamatan dan servis.', 50000, 480, ['brake_check', 'road_test'], 3),
            $this->service('insurance-consultation', '自動車保険のご相談', 'Auto insurance consultation', 'Konsultasi asuransi mobil', '保険の見直しや最適なプランをご提案します。', 'Policy reviews and advice on the right plan.', 'Tinjauan polis dan saran paket yang tepat.', 0, 60, [], 4),
            $this->service('car-sales-consultation', '車探しのご相談', 'Car purchase consultation', 'Konsultasi pembelian mobil', '新車・中古車から、ご希望の一台をお探しします。', 'We find the new or used car you want.', 'Kami carikan mobil baru atau bekas yang Anda inginkan.', 0, 60, [], 4),
            $this->service('tire-change', 'タイヤ交換', 'Tire change', 'Ganti ban', '新品タイヤの取付・交換、季節の履き替えを行います。', 'Fits new tires and handles seasonal changes.', 'Memasang ban baru dan penggantian musiman.', 4000, 60, ['wheel_balancing'], 6),
        ];
    }

    /**
     * @param  list<string>  $inclusions
     * @return array<string, mixed>
     */
    private function service(string $slug, string $ja, string $en, string $id, string $jaText, string $enText, string $idText, int $price, int $minutes, array $inclusions, int $totalUnits): array
    {
        return [
            'slug' => $slug,
            'name' => $ja,
            'description' => $jaText,
            'translations' => [
                'ja' => ['name' => $ja, 'description' => $jaText],
                'en' => ['name' => $en, 'description' => $enText],
                'id' => ['name' => $id, 'description' => $idText],
            ],
            'duration_minutes' => $minutes,
            'vehicle_class' => 'all',
            'base_price' => $price,
            'inclusions' => $inclusions,
            'total_units' => $totalUnits,
        ];
    }

    /**
     * Opens the coming days for booking, skipping Sundays, when the workshop is closed.
     */
    protected function seedInventory(ServiceType $serviceType, int $totalUnits): void
    {
        $start = now('Asia/Tokyo')->startOfDay();

        for ($i = 0; $i < 120; $i++) {
            $date = $start->copy()->addDays($i);

            if ($date->isSunday()) {
                continue;
            }

            ServiceInventory::updateOrCreate(
                ['service_type_id' => $serviceType->id, 'service_date' => $date->toDateString()],
                ['total_units' => $totalUnits, 'price' => $serviceType->base_price]
            );
        }
    }

    private function seedKnowledgeBase(Workshop $workshop): void
    {
        $items = [
            [WorkshopKnowledgeItem::CATEGORY_GENERAL, '吉田自動車工業について', '板金・塗装、整備・車検、自動車保険、車販売、タイヤ販売・交換を行う総合サービスです。', ['overview', '会社'],
                'About Yoshida Automotive', 'A full-service company covering body and paint, maintenance and inspection, auto insurance, car sales, and tires.',
                'Tentang Yoshida Automotive', 'Layanan lengkap untuk bodi dan cat, servis dan inspeksi, asuransi mobil, penjualan mobil, dan ban.'],
            [WorkshopKnowledgeItem::CATEGORY_POLICIES, '営業時間', '営業時間は9:00〜18:00です。日曜・祝日は休業です。', ['hours', '営業時間', 'jam buka'],
                'Opening hours', 'We are open from 9:00 to 18:00 and closed on Sundays and public holidays.',
                'Jam buka', 'Kami buka pukul 09.00 sampai 18.00 dan tutup pada hari Minggu dan libur nasional.'],
            [WorkshopKnowledgeItem::CATEGORY_POLICIES, 'お問い合わせ', 'お電話は042-556-1219です。タイヤサービスの直通番号は04-2937-5296です。', ['phone', '電話', 'telepon', 'contact'],
                'Contact', 'Call us on 042-556-1219. The direct line for tire services is 04-2937-5296.',
                'Kontak', 'Hubungi kami di 042-556-1219. Nomor langsung layanan ban adalah 04-2937-5296.'],
            [WorkshopKnowledgeItem::CATEGORY_FACILITIES, '代車サービス', '修理中の代車を無料でご用意しています（※条件あり）。', ['代車', 'loaner', 'mobil pengganti'],
                'Loaner car', 'A free loaner car is available during repairs (conditions apply).',
                'Mobil pengganti', 'Mobil pengganti gratis tersedia selama perbaikan (syarat berlaku).'],
            [WorkshopKnowledgeItem::CATEGORY_FACILITIES, 'タイヤ専門ショップ X-CHANGE', '吉田自動車工業が提供するタイヤ専門ショップです。公式サイトは https://x-change.tokyo です。', ['tire', 'タイヤ', 'ban', 'x-change'],
                'X-CHANGE tire shop', 'The tire shop run by Yoshida Automotive. Its website is https://x-change.tokyo.',
                'Toko ban X-CHANGE', 'Toko ban yang dikelola Yoshida Automotive. Situsnya https://x-change.tokyo.'],
            [WorkshopKnowledgeItem::CATEGORY_TRANSPORT, 'レッカー対応', '事故時のレッカー・代車対応をご案内します。まずはスタッフにご連絡ください。', ['towing', 'レッカー', 'derek', 'accident', '事故'],
                'Towing', 'We arrange towing and a loaner car after an accident. Please contact our staff first.',
                'Derek', 'Kami mengatur derek dan mobil pengganti setelah kecelakaan. Silakan hubungi staf kami lebih dulu.'],
            [WorkshopKnowledgeItem::CATEGORY_FAQ, '保険を使った修理はできますか？', 'はい、事故修理は保険対応が可能です。', ['insurance', '保険', 'asuransi'],
                'Can repairs be covered by insurance?', 'Yes, accident repairs can be handled through insurance.',
                'Apakah perbaikan bisa memakai asuransi?', 'Ya, perbaikan kecelakaan dapat ditangani melalui asuransi.'],
        ];

        foreach ($items as $sort => [$category, $title, $body, $tags, $enTitle, $enBody, $idTitle, $idBody]) {
            $workshop->knowledgeItems()->updateOrCreate(
                ['title' => $title],
                [
                    'category' => $category,
                    'body' => $body,
                    'translations' => ['en' => ['title' => $enTitle, 'body' => $enBody], 'id' => ['title' => $idTitle, 'body' => $idBody]],
                    'tags' => $tags,
                    'is_active' => true,
                    'sort_order' => $sort,
                ]
            );
        }
    }
}
