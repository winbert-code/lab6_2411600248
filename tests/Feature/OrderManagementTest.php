<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::create(['name' => 'Test Brand', 'slug' => 'test-brand', 'description' => null]);
        $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category', 'description' => null]);

        $this->user = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Test Processor',
            'sku' => 'TEST-CPU-001',
            'description' => 'A test product',
            'quantity' => 20,
            'reorder_level' => 5,
            'unit_price' => 100.00,
            'supplier' => 'Test Supplier',
            'status' => Product::STATUS_ACTIVE,
        ]);
    }

    public function test_receiving_a_purchase_order_adds_stock_and_writes_audit_rows(): void
    {
        $order = PurchaseOrder::create([
            'order_number' => 'PO-TEST-001',
            'supplier' => 'Test Supplier',
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'user_id' => $this->user->id,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_cost' => 80.00,
        ]);

        $this->actingAs($this->user)
            ->post(route('purchase-orders.receive', $order))
            ->assertRedirect(route('purchase-orders.show', $order));

        $this->assertSame(25, $this->product->fresh()->quantity);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $order->fresh()->status);

        $movement = InventoryTransaction::query()->where('reference', 'PO-TEST-001')->firstOrFail();
        $this->assertSame(InventoryTransaction::TYPE_IN, $movement->type);
        $this->assertSame(20, $movement->previous_quantity);
        $this->assertSame(25, $movement->balance_after);

        $this->assertDatabaseHas('activity_logs', [
            'module' => ActivityLog::MODULE_PURCHASE_ORDER,
            'action' => ActivityLog::ACTION_RECEIVED,
        ]);
    }

    public function test_completing_a_sales_order_deducts_stock_and_writes_audit_rows(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'SO-TEST-001',
            'customer' => 'Test Customer',
            'order_date' => now()->toDateString(),
            'status' => SalesOrder::STATUS_PENDING,
            'user_id' => $this->user->id,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 4,
            'unit_price' => 150.00,
        ]);

        $this->actingAs($this->user)
            ->post(route('sales-orders.complete', $order))
            ->assertRedirect(route('sales-orders.show', $order));

        $this->assertSame(16, $this->product->fresh()->quantity);
        $this->assertSame(SalesOrder::STATUS_COMPLETED, $order->fresh()->status);

        $movement = InventoryTransaction::query()->where('reference', 'SO-TEST-001')->firstOrFail();
        $this->assertSame(InventoryTransaction::TYPE_OUT, $movement->type);
        $this->assertSame(20, $movement->previous_quantity);
        $this->assertSame(16, $movement->balance_after);

        $this->assertDatabaseHas('activity_logs', [
            'module' => ActivityLog::MODULE_SALES_ORDER,
            'action' => ActivityLog::ACTION_COMPLETED,
        ]);
    }

    public function test_duplicate_order_lines_are_checked_together_before_completion(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'SO-TEST-002',
            'customer' => 'Test Customer',
            'order_date' => now()->toDateString(),
            'status' => SalesOrder::STATUS_PENDING,
            'user_id' => $this->user->id,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 12,
            'unit_price' => 150.00,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 12,
            'unit_price' => 150.00,
        ]);

        $this->actingAs($this->user)
            ->from(route('sales-orders.show', $order))
            ->post(route('sales-orders.complete', $order))
            ->assertRedirect(route('sales-orders.show', $order))
            ->assertSessionHas('error');

        $this->assertSame(20, $this->product->fresh()->quantity);
        $this->assertSame(SalesOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, InventoryTransaction::query()->count());
    }

    public function test_a_received_purchase_order_cannot_be_edited_again(): void
    {
        $order = PurchaseOrder::create([
            'order_number' => 'PO-TEST-003',
            'supplier' => 'Test Supplier',
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('purchase-orders.edit', $order))
            ->assertForbidden();
    }
}
