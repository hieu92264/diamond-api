<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(CustomerType::class)],
            'include_inactive' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
        ]);

        $query = Customer::query();
        $includeInactive = array_key_exists('include_inactive', $data)
            ? in_array($data['include_inactive'], [true, 1, '1', 'true'], true)
            : false;

        if (! $includeInactive) {
            $query->active();
        }

        if (! empty($data['type'])) {
            $query->type($data['type']);
        }

        if (! empty($data['keyword'])) {
            $query->search($data['keyword']);
        }

        return $this->rawSuccess(
            $query->orderBy('full_name')
                ->get()
                ->map(fn (Customer $customer) => $this->transformCustomer($customer))
                ->all()
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->rawSuccess(
            $this->transformCustomer(Customer::query()->findOrFail($id))
        );
    }

    private function transformCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'code' => $customer->code,
            'type' => $customer->type?->value,
            'full_name' => $customer->full_name,
            'company_name' => $customer->company_name,
            'contact_person' => $customer->contact_person,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'tax_code' => $customer->tax_code,
            'address' => $customer->address,
            'identity_no' => $customer->identity_no,
            'remarks' => $customer->remarks,
            'is_active' => $customer->is_active,
            'created_at' => $customer->created_at?->toISOString(),
            'updated_at' => $customer->updated_at?->toISOString(),
        ];
    }
}
