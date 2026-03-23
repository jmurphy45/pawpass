# PawPass Kennel & Boarding Expansion

PawPass currently serves doggy daycare businesses. This document tracks the expansion to support kennels and boarding facilities — adjacent market, shared infrastructure.

---

## Business Type Matrix

| Business Type | Daycare Features | Boarding Features |
|---|---|---|
| `daycare` | Full | None |
| `kennel` | None | Full |
| `hybrid` | Full | Full |

`tenants.business_type` defaults to `daycare` — no behavior change for existing tenants.

---

## Phase A — Foundation ✅ (in progress)

Data layer and admin API. No payments, no customer portal yet.

**Deliverables:**
- `tenants.business_type` column (daycare / kennel / hybrid)
- `kennel_units` table — physical rooms/runs
- `reservations` table — multi-night bookings per dog
- `KennelAvailabilityService` — overlap detection
- Admin API: `GET|POST|PATCH|DELETE /api/admin/v1/kennel-units`
- Admin API: `GET|POST|GET|PATCH|DELETE /api/admin/v1/reservations`

**Key design decisions:**
- `kennel_unit_id` nullable on reservations (assign unit later)
- Cancellation via `PATCH status: cancelled`; hard DELETE only for `pending`/`cancelled`
- `customer_id` denormalized from `dog->customer_id` at booking time
- KennelUnit uses `is_active` not soft deletes (preserves FK on historical reservations)
- Availability overlap: `existing.starts_at < req.ends_at AND existing.ends_at > req.starts_at`

---

## Phase B — Operations

Visual calendar and care instruction forms.

**Deliverables:**
- Occupancy dashboard (units × date grid)
- Care instruction forms per reservation (feeding schedule, medications, special notes)
- Daily report cards (staff logs activity + photo per dog per day)
- Late checkout / early arrival fee rules
- Add-on services per reservation (extra walk, bath before pickup, grooming)

---

## Phase C — Customer Experience

Customer-facing booking portal.

**Deliverables:**
- Customer portal booking flow (`/my/reservations`)
- Online deposit / hold at booking (Stripe PaymentIntent, captured at check-in)
- Cancellation policy engine (free cancel N days out, partial refund inside N days)
- Waitlist for fully-booked dates
- Booking confirmation + reminder notifications (email + SMS)
- Customer-visible daily report cards

---

## Phase D — Revenue & Intelligence

Monetization features for boarding businesses.

**Deliverables:**
- Dynamic pricing rules (holiday surcharges, peak-season rates)
- Multi-dog discount (same customer, same stay)
- Automated invoicing on checkout
- Boarding revenue in reports (`ReportService` extension)
- Platform plan gating for boarding features (`boarding` plan feature flag)

---

## Technical Notes

- Boarding reservations do NOT consume credits — separate billing model from daycare
- Hybrid tenants have both roster (check-in/credit) and reservations active
- All new tables follow existing conventions: ULID PKs, `timestampTz`, `BelongsToTenant`, PG enums
