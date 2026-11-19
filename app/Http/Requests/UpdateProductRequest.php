<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['sku'] = [
            'required',
            'string',
            'max:64',
            Rule::unique('products', 'sku')->ignore($this->route('product')),
        ];

        return $rules;
    }
}
