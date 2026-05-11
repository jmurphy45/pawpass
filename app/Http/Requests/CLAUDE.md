# Requests — `app/Http/Requests/`

Loaded when editing form request classes.

## Namespace Convention

Mirrors controller namespace: `Admin/V1/`, `Portal/V1/`. One request class per controller action that has non-trivial validation.

## Authorization

`authorize()` must enforce tenant ownership — never skip it:

```php
public function authorize(): bool
{
    return $this->user()->tenant_id === $this->route('model')->tenant_id;
}
```

For platform admin requests (no tenant context): return `$this->user()->role === 'platform_admin'`.

## Validation Rules

- Use `text` field types (no `max:255` unless there's a real constraint)
- Add `bail` to stop on first failure for fields with expensive rules (e.g., `exists:`)
- Money: validate as numeric, store as `numeric` in DB — never floats
- ULIDs: `string|size:26` or `exists:table,id`

## Stripe Webhook Requests

Webhook controllers use raw `$request->getContent()` — do NOT use form requests for webhook endpoints. HMAC verification happens in the controller before any parsing.
