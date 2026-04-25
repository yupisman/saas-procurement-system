<?php
// =============================================================================
// FILE: database/seeders/DatabaseSeeder.php
// PURPOSE: Seed data awal: user roles, kategori, supplier, dan sample PR
//          Jalankan: php artisan db:seed
// =============================================================================
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Category;
use App\Models\PurchaseRequest;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            UserSeeder::class,
            SupplierSeeder::class,
        ]);
    }
}


// =============================================================================
// FILE: database/seeders/CategorySeeder.php
// =============================================================================
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Peralatan & Perlengkapan',    'code' => 'CAT001', 'description' => 'Tools, alat, dan perlengkapan kerja'],
            ['name' => 'Material Bangunan',            'code' => 'CAT002', 'description' => 'Material konstruksi dan bangunan'],
            ['name' => 'Suku Cadang Mesin',            'code' => 'CAT003', 'description' => 'Spare part dan komponen mesin'],
            ['name' => 'Kimia & Pelumas',              'code' => 'CAT004', 'description' => 'Bahan kimia, oli, dan pelumas'],
            ['name' => 'Perlengkapan K3 (APD)',        'code' => 'CAT005', 'description' => 'Alat Pelindung Diri dan keselamatan kerja'],
            ['name' => 'Jasa & Kontraktor',            'code' => 'CAT006', 'description' => 'Layanan jasa dan pekerjaan kontrak'],
            ['name' => 'IT & Elektronik',              'code' => 'CAT007', 'description' => 'Perangkat IT dan elektronik'],
            ['name' => 'Alat Berat & Kendaraan',       'code' => 'CAT008', 'description' => 'Sewa alat berat dan kendaraan'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['code' => $cat['code']], $cat + ['is_active' => true]);
        }

        $this->command->info('✅ Kategori selesai di-seed.');
    }
}


// =============================================================================
// FILE: database/seeders/UserSeeder.php
// =============================================================================
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin ──────────────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@perusahaan.com'],
            [
                'name'      => 'Administrator Sistem',
                'password'  => Hash::make('admin123!'),
                'role'      => 'admin',
                'is_active' => true,
                'phone'     => '081200000001',
            ]
        );

        // ── Purchasing Staff ───────────────────────────────────────────────────
        $purchasingUsers = [
            ['name' => 'Budi Santoso',   'email' => 'budi@perusahaan.com'],
            ['name' => 'Siti Rahayu',    'email' => 'siti@perusahaan.com'],
        ];

        foreach ($purchasingUsers as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name'      => $u['name'],
                    'password'  => Hash::make('purchasing123!'),
                    'role'      => 'purchasing',
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ User admin & purchasing selesai di-seed.');
        $this->command->warn('⚠️  Password default:');
        $this->command->warn('   Admin:      admin@perusahaan.com / admin123!');
        $this->command->warn('   Purchasing: budi@perusahaan.com  / purchasing123!');
    }
}


// =============================================================================
// FILE: database/seeders/SupplierSeeder.php
// =============================================================================
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Category;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'company_name' => 'PT Maju Bersama Jaya',
                'email'        => 'supplier1@mbj.co.id',
                'npwp'         => '01.234.567.8-001.000',
                'pic_name'     => 'Andi Wijaya',
                'pic_phone'    => '08123456001',
                'address'      => 'Jl. Industri Raya No. 10, Kawasan MM2100',
                'city'         => 'Bekasi',
                'province'     => 'Jawa Barat',
                'rating'       => 4.5,
                'categories'   => ['CAT001', 'CAT003'],
            ],
            [
                'company_name' => 'CV Sumber Teknik Nusantara',
                'email'        => 'purchasing@stn.co.id',
                'npwp'         => '02.345.678.9-002.000',
                'pic_name'     => 'Dewi Kusuma',
                'pic_phone'    => '08123456002',
                'address'      => 'Jl. Raya Cikarang Km 5',
                'city'         => 'Cikarang',
                'province'     => 'Jawa Barat',
                'rating'       => 4.2,
                'categories'   => ['CAT002', 'CAT003'],
            ],
            [
                'company_name' => 'PT Karya Mandiri Sejahtera',
                'email'        => 'tender@kms.co.id',
                'npwp'         => '03.456.789.0-003.000',
                'pic_name'     => 'Rudi Hermawan',
                'pic_phone'    => '08123456003',
                'address'      => 'Jl. Gatot Subroto No. 45',
                'city'         => 'Jakarta Selatan',
                'province'     => 'DKI Jakarta',
                'rating'       => 3.8,
                'categories'   => ['CAT004', 'CAT005'],
            ],
            [
                'company_name' => 'PT Amanah Safety Indonesia',
                'email'        => 'sales@amanah-safety.co.id',
                'npwp'         => '04.567.890.1-004.000',
                'pic_name'     => 'Fitri Wulandari',
                'pic_phone'    => '08123456004',
                'address'      => 'Jl. Pemuda No. 88',
                'city'         => 'Surabaya',
                'province'     => 'Jawa Timur',
                'rating'       => 4.7,
                'categories'   => ['CAT005'],
            ],
            [
                'company_name' => 'CV Teknindo Pratama',
                'email'        => 'info@teknindo.co.id',
                'npwp'         => '05.678.901.2-005.000',
                'pic_name'     => 'Hendra Gunawan',
                'pic_phone'    => '08123456005',
                'address'      => 'Ruko Green Garden Blok A No. 12',
                'city'         => 'Balikpapan',
                'province'     => 'Kalimantan Timur',
                'rating'       => 4.0,
                'categories'   => ['CAT006', 'CAT007'],
            ],
        ];

        $categories = Category::pluck('id', 'code');

        foreach ($suppliers as $index => $s) {
            // Buat user login supplier
            $user = User::firstOrCreate(
                ['email' => $s['email']],
                [
                    'name'      => $s['pic_name'],
                    'password'  => Hash::make('supplier123!'),
                    'role'      => 'supplier',
                    'is_active' => true,
                    'phone'     => $s['pic_phone'],
                ]
            );

            // Buat data supplier
            $supplier = Supplier::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'company_name' => $s['company_name'],
                    'npwp'         => $s['npwp'],
                    'pic_name'     => $s['pic_name'],
                    'pic_phone'    => $s['pic_phone'],
                    'address'      => $s['address'],
                    'city'         => $s['city'],
                    'province'     => $s['province'],
                    'status'       => 'aktif',
                    'rating'       => $s['rating'],
                ]
            );

            // Attach kategori
            $catIds = collect($s['categories'])
                        ->map(fn ($code) => $categories[$code] ?? null)
                        ->filter()
                        ->toArray();
            $supplier->categories()->syncWithoutDetaching($catIds);
        }

        $this->command->info('✅ ' . count($suppliers) . ' supplier selesai di-seed.');
        $this->command->warn('⚠️  Password semua supplier: supplier123!');
        $this->command->warn('   Contoh: supplier1@mbj.co.id / supplier123!');
    }
}
