# Hotel Reservation System — Team Demo Plan (7 Members)

The system already has four built-in roles — **Guest, Receptionist (Staff), Manager, Admin** — plus a public-facing site. Instead of splitting randomly, this plan follows the natural life of a hotel booking, so when all 7 members present back-to-back, it tells one continuous story and covers 100% of the system with no overlap and no gaps.

**Demo order:** Member 1 → 2 → 3 → 4 → 5 → 6 → 7 (public site → guest → staff → manager → admin). Presenting in this order means each person's demo naturally hands off to the next.

## Login credentials (from `login.txt`)

| Role | Email | Password |
|---|---|---|
| Admin | admin@hotel.test | Admin@1234 |
| Manager | manager@hotel.test | Manager@1234 |
| Receptionist | reception@hotel.test | Reception@1234 |
| Guest | guest@hotel.test | Guest@1234 |

## Assignment

| # | Member area | Pages / files | What to demo |
|---|---|---|---|
| 1 | **Public website & first impressions** | `index.php`, `rooms.php`, `room-details.php`, `about.php`, `contact.php` | Home page (hero video, facilities), room listing with filters, individual room details page, about & contact pages, light/dark mode toggle, mobile responsiveness. |
| 2 | **Guest signup, login & booking** | `auth/register.php`, `auth/login.php`, `auth/forgot-password.php`, `guest/booking-form.php`, `api/availability.php`, `api/price-quote.php` | Register a new guest account, log in, pick a room and dates, live availability check + price quote, submit a booking. |
| 3 | **Guest self-service dashboard** | `guest/dashboard.php`, `guest/my-bookings.php`, `guest/booking-view.php`, `guest/profile.php`, `guest/review.php` | Guest dashboard overview, viewing/cancelling a booking, editing profile, leaving a review after stay. |
| 4 | **Front desk operations** | `staff/dashboard.php`, `staff/frontdesk.php`, `staff/checkin.php`, `staff/walkin.php` | Receptionist dashboard, checking in the guest booked in step 2, registering a walk-in guest without a prior booking. |
| 5 | **Payments & checkout** | `staff/bookings.php`, `staff/checkout.php`, `staff/payments.php`, `staff/invoice.php` | Managing active bookings, taking payment, processing checkout, generating/printing an invoice. |
| 6 | **Manager oversight** | `manager/dashboard.php`, `manager/approvals.php`, `manager/reports.php`, `manager/reviews.php` | Manager KPI dashboard, approving/rejecting pending items, revenue/occupancy reports, moderating guest reviews. |
| 7 | **Admin: inventory & system control** | `admin/dashboard.php`, `admin/room-types.php`, `admin/rooms.php`, `admin/amenities.php`, `admin/users.php`, `admin/settings.php`, `admin/audit-logs.php` | Admin dashboard, creating a room type (with image upload), Bulk Room Generator, activating/deactivating rooms & room types, managing amenities, managing user accounts, system settings, audit log trail. |

## Notes for a smooth demo

Run `database/seed.sql` beforehand so every member has realistic sample rooms, bookings, and reviews to show instead of an empty system. Members 2–5 depend on each other's data (the booking made in step 2 is checked in during step 4 and paid/checked-out in step 5) — either rehearse together once so the same booking flows through cleanly, or each member can create their own fresh booking to stay independent. Since this is a no-framework PHP project, it's also worth each member briefly pointing out the plain PHP/MySQL code behind their screen (no Laravel/CodeIgniter) if the audience is technical.
