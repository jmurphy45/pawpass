# Resources — `app/Http/Resources/`

Loaded when editing API resource classes.

## Rules

- All resources extend `JsonResource` — **never** `ResourceCollection`
- Collection wrapping is handled by the controller: `ResourceClass::collection($models)`
- Use `$this->whenLoaded('relation')` for optional relationships — never eager-load inside a resource
- Always eager-load in the controller before passing to the resource (N+1 prevention)

## Key Resources

| Resource | Model | Notes |
|---|---|---|
| `DogResource` | `Dog` | Includes `credit_balance` |
| `CustomerResource` | `Customer` | Includes nested dogs when loaded |
| `AttendanceResource` | `Attendance` | Includes check-in/out timestamps |
| `CreditLedgerResource` | `CreditLedger` | Field is `delta` (not `amount`) — map as needed for UI |
| `PackageResource` | `Package` | Includes `type` enum: `one_time`, `subscription`, `unlimited` |
| `OrderResource` | `Order` | Uses `total_amount` (decimal), not `amount_cents` |
| `NotificationResource` | `Notification` | Includes `read_at` for in-app bell |

## Envelope

Controllers wrap resource output in the standard envelope:
```php
return response()->json(['data' => new DogResource($dog)]);
return response()->json(['data' => DogResource::collection($dogs), 'meta' => [...]]);
```
