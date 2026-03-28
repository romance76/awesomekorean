<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ShoppingStore;
use App\Models\ShoppingDeal;

class SeedShoppingData extends Command
{
    protected $signature   = 'shopping:seed';
    protected $description = '한인/미국 마트 샘플 데이터 생성';

    public function handle(): void
    {
        // 마트 목록
        $stores = [
            ['name' => 'H-Mart',          'type' => 'korean',   'region' => 'National',    'website' => 'https://hmart.com',         'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/H_Mart_logo.svg/320px-H_Mart_logo.svg.png'],
            ['name' => 'Zion Market',     'type' => 'korean',   'region' => 'Los Angeles', 'website' => 'https://zionmarket.com',    'logo' => ''],
            ['name' => 'Super H Mart',    'type' => 'korean',   'region' => 'Atlanta',     'website' => 'https://hmart.com',         'logo' => ''],
            ['name' => 'Lotte Plaza',     'type' => 'korean',   'region' => 'NY',          'website' => 'https://lotteplazamarket.com','logo' => ''],
            ['name' => 'Hannam Chain',    'type' => 'korean',   'region' => 'Los Angeles', 'website' => 'https://hannamchain.com',   'logo' => ''],
            ['name' => 'Costco',          'type' => 'american', 'region' => 'National',    'website' => 'https://costco.com',        'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/59/Costco_Wholesale_logo_2010-10-26.svg/320px-Costco_Wholesale_logo_2010-10-26.svg.png'],
            ['name' => 'Kroger',          'type' => 'american', 'region' => 'Atlanta',     'website' => 'https://kroger.com',        'logo' => ''],
            ['name' => 'Walmart',         'type' => 'american', 'region' => 'National',    'website' => 'https://walmart.com',       'logo' => ''],
            ['name' => 'Publix',          'type' => 'american', 'region' => 'Atlanta',     'website' => 'https://publix.com',        'logo' => ''],
            ['name' => 'Mitsuwa Market',  'type' => 'asian',    'region' => 'Los Angeles', 'website' => 'https://mitsuwa.com',       'logo' => ''],
        ];

        foreach ($stores as $s) {
            ShoppingStore::firstOrCreate(['name' => $s['name']], array_merge($s, ['is_active' => true]));
        }
        $this->info('✅ 마트 ' . count($stores) . '개 생성');

        // 딜 샘플
        $hmart = ShoppingStore::where('name', 'H-Mart')->first();
        $costco = ShoppingStore::where('name', 'Costco')->first();
        $kroger = ShoppingStore::where('name', 'Kroger')->first();

        $deals = [
            // H-Mart 딜
            ['store_id'=>$hmart->id,'title'=>'삼겹살 (Pork Belly Sliced)','category'=>'육류','original_price'=>8.99,'sale_price'=>5.99,'unit'=>'lb','image_url'=>'https://images.unsplash.com/photo-1529193591184-b1d58069ecdd?w=400','is_featured'=>true],
            ['store_id'=>$hmart->id,'title'=>'신라면 멀티팩 (5pk)','category'=>'라면/국수','original_price'=>4.99,'sale_price'=>3.49,'unit'=>'pack','image_url'=>'https://images.unsplash.com/photo-1569050467447-ce54b3bbc37d?w=400','is_featured'=>true],
            ['store_id'=>$hmart->id,'title'=>'참기름 (Sesame Oil) 16oz','category'=>'양념/조미료','original_price'=>7.99,'sale_price'=>5.49,'unit'=>'ea','image_url'=>'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=400','is_featured'=>false],
            ['store_id'=>$hmart->id,'title'=>'떡볶이 떡 (Rice Cake)','category'=>'떡/빵','original_price'=>3.99,'sale_price'=>2.79,'unit'=>'pack','image_url'=>'https://images.unsplash.com/photo-1583224994559-0e5dffb3a0f8?w=400','is_featured'=>false],
            ['store_id'=>$hmart->id,'title'=>'김치 (Kimchi) 5lb','category'=>'반찬/김치','original_price'=>15.99,'sale_price'=>11.99,'unit'=>'ea','image_url'=>'https://images.unsplash.com/photo-1590301157890-4810ed352733?w=400','is_featured'=>true],
            ['store_id'=>$hmart->id,'title'=>'한우 갈비 (Beef Short Rib)','category'=>'육류','original_price'=>14.99,'sale_price'=>10.99,'unit'=>'lb','image_url'=>'https://images.unsplash.com/photo-1544025162-d76694265947?w=400','is_featured'=>false],
            ['store_id'=>$hmart->id,'title'=>'고추장 (Red Pepper Paste) 1kg','category'=>'양념/조미료','original_price'=>8.49,'sale_price'=>6.29,'unit'=>'ea','image_url'=>'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=400','is_featured'=>false],
            ['store_id'=>$hmart->id,'title'=>'냉동 만두 (Frozen Dumplings)','category'=>'냉동식품','original_price'=>6.99,'sale_price'=>4.99,'unit'=>'pack','image_url'=>'https://images.unsplash.com/photo-1498654896293-37aacf113fd9?w=400','is_featured'=>false],

            // Costco 딜
            ['store_id'=>$costco->id,'title'=>'Kirkland Salmon Fillet 3lb','category'=>'생선/해산물','original_price'=>34.99,'sale_price'=>26.99,'unit'=>'pack','image_url'=>'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=400','is_featured'=>true],
            ['store_id'=>$costco->id,'title'=>'Organic Chicken Breast 6lb','category'=>'육류','original_price'=>22.99,'sale_price'=>17.99,'unit'=>'pack','image_url'=>'https://images.unsplash.com/photo-1604503468506-a8da13d11d36?w=400','is_featured'=>false],
            ['store_id'=>$costco->id,'title'=>'Blueberries 18oz 2-pack','category'=>'과일/채소','original_price'=>10.99,'sale_price'=>7.99,'unit'=>'pack','image_url'=>'https://images.unsplash.com/photo-1611080626919-7cf5a9dbab12?w=400','is_featured'=>false],
            ['store_id'=>$costco->id,'title'=>'Tide PODS Laundry (152ct)','category'=>'생활용품','original_price'=>29.99,'sale_price'=>21.99,'unit'=>'ea','image_url'=>'https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=400','is_featured'=>true],

            // Kroger
            ['store_id'=>$kroger->id,'title'=>'USDA Choice Beef Ground 3lb','category'=>'육류','original_price'=>12.99,'sale_price'=>8.99,'unit'=>'pack','image_url'=>'https://images.unsplash.com/photo-1602473595058-14e597c5ad3e?w=400','is_featured'=>false],
            ['store_id'=>$kroger->id,'title'=>'Strawberries 2lb','category'=>'과일/채소','original_price'=>5.99,'sale_price'=>3.99,'unit'=>'pack','image_url'=>'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=400','is_featured'=>false],
        ];

        $today    = now()->toDateString();
        $nextWeek = now()->addDays(7)->toDateString();

        foreach ($deals as $d) {
            ShoppingDeal::firstOrCreate(
                ['store_id' => $d['store_id'], 'title' => $d['title']],
                array_merge($d, ['valid_from' => $today, 'valid_until' => $nextWeek, 'is_active' => true])
            );
        }
        $this->info('✅ 딜 ' . count($deals) . '개 생성');
        $this->info('완료!');
    }
}
