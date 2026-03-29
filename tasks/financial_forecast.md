# PawPass — Realistic Financial Forecast
**Date:** March 2026 | **Model:** Seed-stage, post-funding GTM launch

---

## Section 1: Core Assumptions

### 1.1 Pricing (from pricing.md)

| Plan     | Monthly | Annual  | Stripe Platform Fee |
|----------|---------|---------|---------------------|
| Free     | $0      | $0      | 5%                  |
| Starter  | $49     | $470    | 5%                  |
| Pro      | $99     | $950    | 5%                  |
| Business | $199    | $1,910  | 5%                  |

---

### 1.2 GMV Assumptions Per Tenant Per Month

GMV is the gross payment volume flowing through Stripe Connect per tenant.
PawPass earns a 5% platform fee on all orders (day packs + subscription renewals).

| Plan     | Avg Dogs | Avg Pack Price | Avg Packs Sold/Mo | Est. Monthly GMV | 5% Fee |
|----------|----------|----------------|-------------------|------------------|--------|
| Starter  | 18       | $150           | 22                | $5,000           | $250   |
| Pro      | 40       | $175           | 45                | $12,000          | $600   |
| Business | 85       | $200           | 95                | $30,000          | $1,500 |

*Notes:*
- *Pack = one-time day pack purchase (e.g. 10-visit pack at $150)*
- *Subscription renewals also pass through Stripe Connect and generate fee revenue*
- *GMV ramps up 10% per quarter as tenants onboard more customers*

---

### 1.3 Blended Revenue Per Tenant Per Month

| Plan     | SaaS   | Fee    | Total  | Weight | Weighted |
|----------|--------|--------|--------|--------|----------|
| Starter  | $49    | $250   | $299   | 55%    | $164     |
| Pro      | $99    | $600   | $699   | 35%    | $245     |
| Business | $199   | $1,500 | $1,699 | 10%    | $170     |
| **Blended** |     |        |        |        | **$579** |

*Plan mix shifts over time. Year 1: 55/35/10. Year 3: 45/40/15 (upsell effect).*

---

### 1.4 Tenant Acquisition Assumptions

| Channel           | Y1 Mix | CAC   | Notes                                |
|-------------------|--------|-------|--------------------------------------|
| Outbound (direct) | 60%    | $350  | Cold email, IG ads to business owners|
| Inbound (SEO/content) | 20% | $150  | Blog, YouTube, Google Ads            |
| Referral/word-of-mouth | 15% | $80  | Built-in referral program at month 6 |
| Channel partners  | 5%     | $200  | Pet industry associations, franchises |

**Blended CAC (Y1):** ~$280
**Blended CAC (Y2):** ~$220 (inbound growing)
**Blended CAC (Y3):** ~$175 (inbound dominant)

---

### 1.5 Churn Assumptions

| Metric                  | Rate   | Reasoning                                                          |
|-------------------------|--------|--------------------------------------------------------------------|
| Monthly logo churn      | 2.5%   | SMB SaaS benchmark; high switching cost (credit ledger, subdomain) |
| Monthly revenue churn   | 1.8%   | Negative net dollar churn expected by Y2 from upsells              |
| Annual logo churn       | ~26%   | Typical SMB vertical SaaS range                                    |

*Key churn driver: business closures (seasonal daycares), not product dissatisfaction.*
*Mitigation: annual plan discount (20% off) locks in 12-month commitment.*

---

### 1.6 Cost of Revenue (COGS)

Per-tenant variable costs per month:

| Line Item              | Starter | Pro   | Business |
|------------------------|---------|-------|----------|
| Stripe Connect fees    | $15     | $36   | $90      |
| Twilio SMS             | $0      | $8    | $20      |
| Resend email           | $1      | $2    | $3       |
| Meilisearch            | $1      | $2    | $4       |
| PostgreSQL/Redis/infra | $3      | $5    | $8       |
| **Total COGS/tenant**  | **$20** | **$53**| **$125**|

**Gross margin per tenant:**
- Starter: ($299 - $20) / $299 = **93%**
- Pro: ($699 - $53) / $699 = **92%**
- Business: ($1,699 - $125) / $1,699 = **93%**
- **Blended gross margin: ~92%**

*Note: Stripe Connect fee is estimated at 0.3% of GMV (platform account fee, not full Stripe processing).*

---

## Section 2: Year 1 Monthly Model (April 2026 – March 2027)

### Assumptions
- Funding closes April 2026
- GTM begins April 2026
- 2 founders drawing $8,000/mo each
- First hire (Sales/CS) at month 4: $6,500/mo
- Second hire (Engineer) at month 7: $9,000/mo
- Infrastructure base cost: $2,500/mo (server, tooling, monitoring)

### Monthly Tenant Build

| Month | New | Churned | End Total | Plan Mix (S/P/B)  |
|-------|-----|---------|-----------|-------------------|
| Apr   | 6   | 0       | 6         | 4 / 2 / 0         |
| May   | 8   | 0       | 14        | 8 / 5 / 1         |
| Jun   | 10  | 0       | 24        | 13 / 8 / 3        |
| Jul   | 13  | 1       | 36        | 20 / 12 / 4       |
| Aug   | 15  | 1       | 50        | 27 / 18 / 5       |
| Sep   | 18  | 1       | 67        | 37 / 23 / 7       |
| Oct   | 22  | 2       | 87        | 48 / 30 / 9       |
| Nov   | 25  | 2       | 110       | 60 / 38 / 12      |
| Dec   | 20  | 3       | 127       | 70 / 44 / 13      |
| Jan   | 25  | 3       | 149       | 82 / 52 / 15      |
| Feb   | 28  | 4       | 173       | 95 / 60 / 18      |
| Mar   | 30  | 4       | 199       | 109 / 70 / 20     |

*December dip reflects slower SMB onboarding in holiday season.*

### Monthly Revenue

| Month | SaaS MRR | Fee MRR  | Total MRR | Cumulative ARR |
|-------|----------|----------|-----------|----------------|
| Apr   | $395      | $1,450   | $1,845    | $22K           |
| May   | $922      | $3,380   | $4,302    | $52K           |
| Jun   | $1,580    | $5,800   | $7,380    | $89K           |
| Jul   | $2,370    | $8,690   | $11,060   | $133K          |
| Aug   | $3,290    | $12,070  | $15,360   | $184K          |
| Sep   | $4,410    | $16,180  | $20,590   | $247K          |
| Oct   | $5,735    | $21,030  | $26,765   | $321K          |
| Nov   | $7,250    | $26,600  | $33,850   | $406K          |
| Dec   | $8,370    | $30,710  | $39,080   | $469K          |
| Jan   | $9,820    | $36,040  | $45,860   | $550K          |
| Feb   | $11,400   | $41,840  | $53,240   | $639K          |
| Mar   | $13,130   | $48,160  | $61,290   | $735K          |

**End of Year 1 Snapshot:**
- Tenants: 199
- MRR: $61,290
- ARR run rate: $735K
- Cumulative revenue: ~$320K

---

### Monthly Expenses (Year 1)

| Month | Salaries | Infra+COGS | Marketing | Other | Total OpEx |
|-------|----------|------------|-----------|-------|------------|
| Apr   | $16,000  | $2,620     | $8,000    | $1,500| $28,120    |
| May   | $16,000  | $2,780     | $9,000    | $1,500| $29,280    |
| Jun   | $16,000  | $3,040     | $10,000   | $1,500| $30,540    |
| Jul   | $22,500  | $3,470     | $12,000   | $2,000| $39,970    |
| Aug   | $22,500  | $3,900     | $12,000   | $2,000| $40,400    |
| Sep   | $22,500  | $4,510     | $12,000   | $2,000| $41,010    |
| Oct   | $31,500  | $5,190     | $14,000   | $2,500| $53,190    |
| Nov   | $31,500  | $6,000     | $14,000   | $2,500| $54,000    |
| Dec   | $31,500  | $6,430     | $10,000   | $2,500| $50,430    |
| Jan   | $31,500  | $7,290     | $16,000   | $2,500| $57,290    |
| Feb   | $31,500  | $8,220     | $18,000   | $2,500| $60,220    |
| Mar   | $31,500  | $9,160     | $20,000   | $3,000| $63,660    |

*Marketing = paid acquisition + content tools. "Other" = legal, accounting, software.*

### Year 1 P&L Summary

| Line              | Amount       |
|-------------------|--------------|
| Total Revenue     | $320,620     |
| Total COGS        | $58,000      |
| Gross Profit      | $262,620     |
| Total OpEx        | $547,110     |
| **Net Loss (Y1)** | **($284,490)**|

*Assumes $500K seed funding. Remaining runway: ~$215K entering Y2.*

---

## Section 3: Annual Summary (Years 1–3)

### Tenant Growth

| Year   | Start | New Added | Churned | End   | Avg Active |
|--------|-------|-----------|---------|-------|------------|
| Year 1 | 0     | 220       | 21      | 199   | 85         |
| Year 2 | 199   | 620       | 133     | 686   | 430        |
| Year 3 | 686   | 1,600     | 356     | 1,930 | 1,250      |

*Year 2 churn improves slightly as annual plans are pushed harder.*
*Year 3: inbound/referral dominant; CAC drops; growth accelerates.*

---

### Revenue Breakdown

| Year   | SaaS Revenue | Fee Revenue  | Total Revenue | YoY Growth |
|--------|--------------|--------------|---------------|------------|
| Year 1 | $83,000      | $237,000     | $320,000      | —          |
| Year 2 | $870,000     | $2,360,000   | $3,230,000    | 910%       |
| Year 3 | $2,780,000   | $7,240,000   | $10,020,000   | 210%       |

*Transaction fees represent ~74% of total revenue — this scales with tenant GMV growth, not just tenant count.*

---

### P&L Summary (Annual)

| Line                  | Year 1       | Year 2       | Year 3       |
|-----------------------|--------------|--------------|--------------|
| Revenue               | $320,000     | $3,230,000   | $10,020,000  |
| COGS (variable)       | $58,000      | $380,000     | $960,000     |
| **Gross Profit**      | $262,000     | $2,850,000   | $9,060,000   |
| **Gross Margin**      | 82%          | 88%          | 90%          |
| Salaries              | $333,000     | $1,200,000   | $2,800,000   |
| Marketing / CAC       | $155,000     | $660,000     | $1,200,000   |
| Infrastructure        | $42,000      | $96,000      | $180,000     |
| G&A (legal/acctg)     | $24,000      | $60,000      | $100,000     |
| **Total OpEx**        | $554,000     | $2,016,000   | $4,280,000   |
| **EBITDA**            | **($292,000)**| **$834,000**| **$4,780,000**|
| **EBITDA Margin**     | —            | 26%          | 48%          |

---

### Ending ARR Run Rate

| Year   | Tenants | Blended Rev/Tenant | MRR      | ARR Run Rate |
|--------|---------|---------------------|----------|--------------|
| Year 1 | 199     | $308                | $61,000  | $735K        |
| Year 2 | 686     | $450                | $309,000 | $3.7M        |
| Year 3 | 1,930   | $579                | $1,118,000| $13.4M      |

*Blended rev/tenant increases year-over-year due to:*
1. *Upsell to higher tiers (plan mix shift: more Pro/Business over time)*
2. *GMV growth per tenant as their customer base matures (+10%/yr assumed)*
3. *Subscription billing volume compounding through the platform*

---

## Section 4: Unit Economics

### LTV Calculation

Average tenant lifetime = 1 / 2.5% monthly churn = **40 months**

| Plan     | Rev/Mo | Gross Margin | GM/Mo | LTV (40mo) |
|----------|--------|--------------|-------|------------|
| Starter  | $299   | 93%          | $278  | $11,120    |
| Pro      | $699   | 92%          | $643  | $25,720    |
| Business | $1,699 | 93%          | $1,580| $63,200    |
| **Blended** | $579 | 92%         | $533  | **$21,320**|

### LTV:CAC

| Year   | Blended LTV | Blended CAC | LTV:CAC | Payback Period |
|--------|-------------|-------------|---------|----------------|
| Year 1 | $21,320     | $280        | 76:1    | ~6 months      |
| Year 2 | $21,320     | $220        | 97:1    | ~5 months      |
| Year 3 | $24,000     | $175        | 137:1   | ~4 months      |

*High LTV:CAC driven by the transaction fee revenue stream — most vertical SaaS sees 5–10:1; PawPass's payment take-rate dramatically improves this ratio.*

---

## Section 5: Key SaaS Metrics Dashboard

| Metric                  | Year 1 Exit | Year 2 Exit | Year 3 Exit |
|-------------------------|-------------|-------------|-------------|
| Tenants                 | 199         | 686         | 1,930       |
| MRR                     | $61K        | $309K       | $1.12M      |
| ARR Run Rate            | $735K       | $3.7M       | $13.4M      |
| Monthly Churn (logo)    | 2.5%        | 2.3%        | 2.0%        |
| Net Revenue Retention   | 105%        | 110%        | 115%        |
| Gross Margin            | 82%         | 88%         | 90%         |
| EBITDA Margin           | (91%)       | 26%         | 48%         |
| Blended CAC             | $280        | $220        | $175        |
| LTV:CAC                 | 76:1        | 97:1        | 137:1       |
| Cash Burn / Month (avg) | $24K        | —           | —           |

*Net Revenue Retention >100% by Year 2 because upsells and GMV growth outpace churn dollars.*

---

## Section 6: Funding & Runway

### Seed Round ($750K assumed)

| Quarter      | Revenue  | Burn     | Net Cash Flow | Cumulative Cash |
|--------------|----------|----------|---------------|-----------------|
| Q1 (Apr-Jun) | $13,500  | $88,000  | ($74,500)     | $675,500        |
| Q2 (Jul-Sep) | $47,000  | $121,000 | ($74,000)     | $601,500        |
| Q3 (Oct-Dec) | $99,700  | $157,600 | ($57,900)     | $543,600        |
| Q4 (Jan-Mar) | $160,400 | $181,200 | ($20,800)     | $522,800        |

**Cash at end of Year 1: ~$523K**
**Monthly burn approaching breakeven by Q2 Year 2**

### Series A Readiness (Target: Q3 Year 2)
- $3M+ ARR
- >100% NRR
- <2% monthly churn
- Proven CAC payback < 6 months
- Gross margin >85%

*At these metrics, a $5–8M Series A at 8–10x ARR multiple = $24–37M valuation is reasonable.*

---

## Section 7: Sensitivity Analysis

### What Changes the Model Most

**Upside scenarios:**
- GMV/tenant higher than modeled (larger daycares, boarding add-ons charged = more fee revenue)
- Faster upsell to Pro/Business tier (each Starter→Pro conversion = +$400/mo revenue)
- Annual plan adoption >50% (reduces churn, locks in cash)
- White-label deals (franchise networks) → 1 deal = 10–50 tenants instantly

**Downside scenarios:**
- Monthly churn rises to 4–5% (SMB closures, recession sensitivity)
- GMV lower than modeled (smaller daycares, less pack purchasing)
- Longer sales cycles (SMB owners are slow to switch)

### Churn Sensitivity on ARR (Year 2)

| Monthly Churn | Year 2 Tenants | Year 2 ARR |
|---------------|----------------|------------|
| 1.5%          | 810            | $4.4M      |
| 2.5% (base)   | 686            | $3.7M      |
| 4.0%          | 540            | $2.9M      |
| 5.5%          | 430            | $2.3M      |

### GMV Sensitivity on Year 2 Revenue

| Avg GMV per Starter | Fee Revenue | Total Y2 Revenue |
|---------------------|-------------|-----------------|
| $3,000/mo           | $1,570,000  | $2,440,000      |
| $5,000/mo (base)    | $2,360,000  | $3,230,000      |
| $8,000/mo           | $3,780,000  | $4,650,000      |
| $12,000/mo          | $5,670,000  | $6,540,000      |

---

## Section 8: Key Milestones

| Milestone                        | Target Date    | Revenue Signal       |
|----------------------------------|----------------|----------------------|
| First 10 paying tenants          | Jun 2026       | $5,800 MRR           |
| First $10K MRR                   | Jul 2026       | 36 tenants           |
| First $50K MRR                   | Nov 2026       | 110 tenants          |
| Cash flow breakeven              | Q2 Year 2      | ~$85K MRR            |
| $1M ARR                          | Q3 Year 2      | 145 tenants          |
| $3M ARR                          | Q1 Year 3      | ~430 tenants         |
| Series A ready                   | Q3 Year 2      | $3M+ ARR, >100% NRR  |

---

## Assumptions Checklist

Before using this forecast in investor conversations, validate:

- [ ] Confirm actual Stripe Connect fee rate (0.25% + $0.25 vs 0.3% used here)
- [ ] Survey 5–10 target daycares on average monthly GMV to validate $5K Starter estimate
- [ ] Validate $49 Starter price sensitivity with 10 cold outreach conversations
- [ ] Confirm Twilio SMS cost per segment at volume (estimate: $0.0079/segment)
- [ ] Confirm Resend pricing at 100K–500K emails/mo
- [ ] Test annual plan conversion rate — target >30% of new signups
- [ ] Establish baseline CAC from first 30 days of paid acquisition

---

*Model version 1.0 — March 2026. Refresh quarterly as actuals come in.*
