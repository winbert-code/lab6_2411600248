<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportAndProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Brand::create(['name' => 'Test Brand', 'slug' => 'test-brand', 'description' => null]);
        Category::create(['name' => 'Test Category', 'slug' => 'test-category', 'description' => null]);
    }

    private function user(string $role): User
    {
        return User::forceCreate([
            'name' => ucfirst($role).' User',
            'email' => $role.'@example.test',
            'password' => bcrypt('password123'),
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function product(string $name, string $sku, float $price = 100.00): Product
    {
        return Product::create([
            'brand_id' => 1,
            'category_id' => 1,
            'name' => $name,
            'sku' => $sku,
            'quantity' => 10,
            'reorder_level' => 4,
            'unit_price' => $price,
            'status' => Product::STATUS_ACTIVE,
        ]);
    }

    public function test_product_report_shows_stock_value_and_filtered_movements(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $product = $this->product('Test Processor', 'REP-CPU-001');
        $this->product('Hidden Keyboard', 'REP-KB-001', 50.00);

        InventoryTransaction::create([
            'product_id' => $product->id,
            'type' => InventoryTransaction::TYPE_IN,
            'quantity' => 5,
            'reference' => 'PO-REPORT-001',
            'user_id' => $admin->id,
        ]);
        InventoryTransaction::create([
            'product_id' => $product->id,
            'type' => InventoryTransaction::TYPE_OUT,
            'quantity' => 2,
            'reference' => 'SO-REPORT-001',
            'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.products', ['search' => 'Processor']));

        $response->assertOk()
            ->assertSee('Product Report')
            ->assertSee('Test Processor')
            ->assertSee('REP-CPU-001')
            ->assertSee('13')
            ->assertSee('1,300.00')
            ->assertDontSee('Hidden Keyboard');
    }

    public function test_sales_and_purchase_report_filters_orders_and_shows_totals(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $product = $this->product('Test Processor', 'REP-CPU-002');
        $today = now()->toDateString();

        $sales = SalesOrder::create([
            'order_number' => 'SO-REPORT-100',
            'customer' => 'Report Customer',
            'order_date' => $today,
            'status' => SalesOrder::STATUS_COMPLETED,
            'user_id' => $admin->id,
        ]);
        $sales->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50.00,
        ]);

        $purchase = PurchaseOrder::create([
            'order_number' => 'PO-REPORT-100',
            'supplier' => 'Report Supplier',
            'order_date' => $today,
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'user_id' => $admin->id,
        ]);
        $purchase->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_cost' => 20.00,
        ]);

        $oldSale = SalesOrder::create([
            'order_number' => 'SO-REPORT-OLD',
            'customer' => 'Old Customer',
            'order_date' => now()->subDays(10)->toDateString(),
            'status' => SalesOrder::STATUS_PENDING,
            'user_id' => $admin->id,
        ]);
        $oldSale->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10.00,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.orders', [
            'from' => $today,
            'to' => $today,
            'sales_status' => SalesOrder::STATUS_COMPLETED,
            'purchase_status' => PurchaseOrder::STATUS_RECEIVED,
        ]));

        $response->assertOk()
            ->assertSee('Sales &amp; Purchase Report', false)
            ->assertSee('SO-REPORT-100')
            ->assertSee('PO-REPORT-100')
            ->assertSee('100.00')
            ->assertSee('60.00')
            ->assertDontSee('SO-REPORT-OLD');
    }

    public function test_daily_report_history_groups_activity_by_day(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $product = $this->product('Test Processor', 'REP-CPU-003');
        $today = now()->toDateString();

        InventoryTransaction::create([
            'product_id' => $product->id,
            'type' => InventoryTransaction::TYPE_IN,
            'quantity' => 5,
            'reference' => 'PO-DAILY-001',
            'user_id' => $admin->id,
        ]);

        $sale = SalesOrder::create([
            'order_number' => 'SO-DAILY-001',
            'customer' => 'Daily Customer',
            'order_date' => $today,
            'status' => SalesOrder::STATUS_COMPLETED,
            'user_id' => $admin->id,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 75.00,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.daily', [
            'from' => $today,
            'to' => $today,
        ]));

        $response->assertOk()
            ->assertSee('Daily Report History')
            ->assertSee(now()->format('M d, Y'))
            ->assertSee('75.00')
            ->assertSee('Units in');
    }

    public function test_staff_can_update_own_profile_without_changing_role(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff)->get(route('profile.edit'))->assertOk();

        $response = $this->actingAs($staff)->patch(route('profile.update'), [
            'name' => 'Updated Staff',
            'email' => 'updated-staff@example.test',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'role' => User::ROLE_ADMIN,
        ]);

        $response->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Your profile was updated.');

        $staff->refresh();

        $this->assertSame('Updated Staff', $staff->name);
        $this->assertSame('updated-staff@example.test', $staff->email);
        $this->assertSame(User::ROLE_STAFF, $staff->role);
        $this->assertTrue(Hash::check('newpassword123', $staff->password));
    }

    public function test_report_routes_respect_staff_and_admin_roles(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff);
        $this->get(route('reports.products'))->assertOk();
        $this->get(route('profile.edit'))->assertOk();
        $this->get(route('reports.orders'))->assertForbidden();
        $this->get(route('reports.daily'))->assertForbidden();

        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin);
        $this->get(route('reports.products'))->assertOk();
        $this->get(route('profile.edit'))->assertOk();
        $this->get(route('reports.orders'))->assertOk();
        $this->get(route('reports.daily'))->assertOk();
    }
}
