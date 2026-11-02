# Yoshida Automotive（吉田自動車工業）

Situs perusahaan bengkel dengan **AI Concierge**, dibangun dengan Laravel. Pelanggan melihat lima divisi (板金・塗装, 整備・車検, 自動車保険, 車販売, タイヤ X-Change), bertanya ke AI, lalu mengirim permintaan servis yang dikonfirmasi staf lewat panel admin. Tersedia tema terang dan gelap, serta bahasa Jepang (utama), Inggris, dan Indonesia.

## Fitur

- **Beranda** dengan panel AI Concierge dan kartu lima divisi.
- **Panel divisi** (`/{slug}/divisions/{divisi}`) dan **会社案内** (`/{slug}/company`). Isi detailnya berbahasa Jepang, ada di [app/Services/WorkshopDivisions.php](app/Services/WorkshopDivisions.php).
- **Reservasi**: tanggal masuk, tanggal ambil (opsional untuk servis sehari), kendaraan, nomor polisi, dan kontak. Harga tetap per kendaraan; kendaraan memakai satu slot bengkel setiap hari selama dikerjakan, dan hari tutup dilewati.
- **AI Concierge** ([app/Services/Advisor](app/Services/Advisor)): menjawab dari data bengkel lewat tool, membuat permintaan booking, dan meneruskan ke staf.
- **Admin** (`/admin`): layanan, kapasitas harian, knowledge base, booking, handover, member, tim, analitik.

## Menjalankan

```bash
composer install
npm ci && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Buka `http://localhost:8000`. Login admin demo: `owner@yoshida-automotive.test`, kata sandi `password`.

AI Concierge butuh endpoint yang kompatibel dengan OpenAI: isi `LOCAL_LLM_BASE_URL`, `LOCAL_LLM_API_KEY`, dan `LOCAL_LLM_MODEL` di `.env`.

Test:

```bash
php -d memory_limit=-1 vendor/bin/phpunit
```

## Yang perlu diisi sebelum dipakai

- **Foto**: taruh file WebP di `public/images/yoshida/` (`hero`, `body-paint`, `maintenance`, `insurance`, `sales`, `tires`, `president`).
- **Harga, kapasitas, dan stok mobil** di [DemoWorkshopSeeder](database/seeders/DemoWorkshopSeeder.php) dan `WorkshopDivisions` masih contoh.
- **Alamat dan peta** belum ada di data.
- **Hari libur nasional** belum ditutup otomatis; atur lewat admin inventori.
- **Deploy** hanya manual dari GitHub Actions, setelah secret `PLESK_*` diisi.
