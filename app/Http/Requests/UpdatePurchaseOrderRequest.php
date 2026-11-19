<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends StorePurchaseOrderRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['order_number'] = [
            'nullable',
            'string',
            'max:32',
            Rule::unique('purchase_orders', 'order_number')->ignore($this->route('purchaseOrder')),
        ];

        return $rules;
    }
}
