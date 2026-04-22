<?php

namespace App\Http\Services;

use App\Http\Interfaces\EmployeeServiceInterface;
use App\Models\Employee;
use App\Support\Concerns\BaseService;

class EmployeeService extends BaseService implements EmployeeServiceInterface
{
    public function __construct(Employee $employee, array $relations = [])
    {
        parent::__construct(
            model: $employee,
            relations: $relations
        );
    }

    /**
     * @throws \Throwable
     */
    public function create(array $data): array
    {
        $employeeCode = $this->model->max('employee_code');
        $data['employee_code'] = $employeeCode ? 'EMP' . str_pad((int)substr($employeeCode, 3) + 1, 4, '0', STR_PAD_LEFT) : 'EMP0001';
        return parent::create($data);
    }
}

