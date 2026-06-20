\# Product Requirement Document (PRD)

\## Centralized Payment Gateway Service (payment.naeva.id)



\---



\## 1. Document Control

\* \*\*Author:\*\* AI Collaborator (Gemini)

\* \*\*Status:\*\* Draft / Ready for Review

\* \*\*Date:\*\* June 20, 2026

\* \*\*Target Release:\*\* Q3 2026



\---



\## 2. Executive Summary \& Objective

\### Background

Saat ini, Naeva memiliki beberapa proyek/aplikasi independen yang membutuhkan fitur pembayaran online. Memasang SDK Midtrans dan mengonfigurasi callback di setiap proyek secara terpisah tidak efisien, menyulitkan monitoring transaksi, dan memperpanjang waktu maintenance jika terjadi perubahan API dari pihak penyedia payment gateway.



\### Objective

Membangun satu layanan pembayaran terpusat (\*Centralized Payment Service\*) di subdomain `payment.naeva.id`. Sistem ini akan bertindak sebagai \*hub\* atau \*bridge\* antara semua proyek internal Naeva (Client Apps) dengan Midtrans. 



\### Key Benefits

\* \*\*Single Integration:\*\* Proyek lain cukup terintegrasi dengan `payment.naeva.id` via REST API yang seragam.

\* \*\*Centralized Monitoring:\*\* Satu dashboard untuk melihat semua transaksi dari berbagai proyek.

\* \*\*Security:\*\* Kredensial Midtrans (Server Key/Client Key) hanya disimpan di satu server aman.



\---



\## 3. User Personas

1\.  \*\*End-User (Customer):\*\* Pengguna yang melakukan transaksi di salah satu proyek Naeva dan melakukan pembayaran.

2\.  \*\*Internal Developer (Naeva Team):\*\* Developer yang mengintegrasikan aplikasi baru ke dalam ekosistem `payment.naeva.id`.

3\.  \*\*Administrator/Owner:\*\* Anda sendiri, yang memantau total pendapatan, status transaksi, dan mengelola konfigurasi proyek.



\---



\## 4. Architecture \& High-Level Flow

Sistem ini menggunakan arsitektur \*\*Multi-Tenant (App-ID based)\*\*. Setiap proyek yang ingin menggunakan layanan ini harus didaftarkan dan akan mendapatkan `App-ID` serta `Secret-Key` unik.



\### Workflow Transaksi:

1\.  \*\*Client App\*\* menembak API `payment.naeva.id` dengan membawa `App-ID`, data transaksi, dan `callback\_url` internal mereka.

2\.  \*\*Payment Service\*\* memvalidasi request, menyimpan draf transaksi ke database, lalu membuat \*Snap Token\* ke Midtrans.

3\.  \*\*Payment Service\*\* mengembalikan URL pembayaran Snap ke \*\*Client App\*\*.

4\.  User melakukan pembayaran di halaman Midtrans.

5\.  Midtrans mengirimkan \*\*HTTP Notification (Webhook)\*\* ke `payment.naeva.id`.

6\.  \*\*Payment Service\*\* memvalidasi notifikasi tersebut, memperbarui status di database internal, lalu meneruskan (\*forward\*) status pembayaran ke `callback\_url` milik \*\*Client App\*\* yang bersangkutan.



\---



\## 5. Functional Requirements (Fitur \& Spesifikasi)



\### 5.1 Project \& Tenant Management (Backend Admin)

\* Sistem harus bisa mendaftarkan proyek baru (Contoh: Project A, Project B).

\* Setiap proyek memiliki:

&#x20;   \* `project\_id` / `app\_id` (String unik)

&#x20;   \* `secret\_key` (Untuk autentikasi API antar-server)

&#x20;   \* `default\_callback\_url` (Fallback jika client app tidak mengirimkan callback URL spesifik)



\### 5.2 Transaction \& Token Creation API

\* Endpoint untuk membuat transaksi baru (menginisiasi Midtrans Snap).

\* Menerima detail produk, gross amount, informasi customer, dan data custom (metadata) untuk kebutuhan proyek masing-masing.

\* Menghasilkan `redirect\_url` dan `token` Midtrans.



\### 5.3 Webhook Handling \& Forwarding Engine (Krusial)

\* Endpoint khusus untuk menerima notifikasi otomatis dari Midtrans (`/api/v1/callback/midtrans`).

\* Sistem harus memverifikasi keaslian signature key dari Midtrans sebelum memproses data.

\* Sistem memperbarui status transaksi di database (`settlement`, `pending`, `expire`, `deny`).

\* \*\*Forwarding Mechanism:\*\* Sistem harus otomatis mencari tahu transaksi tersebut milik proyek mana berdasarkan ID transaksi, lalu mengirimkan HTTP POST berisi status terbaru ke `callback\_url` proyek tersebut secara \*asynchronous\* (disarankan menggunakan Queue/Job Worker seperti Redis/RabbitMQ agar toleran terhadap kegagalan jaringan).



\### 5.4 Dashboard Monitoring (Minimalis)

\* Halaman internal untuk melihat list transaksi global.

\* Filter transaksi berdasarkan `Project/App-ID`.

\* Status kebersihan webhook (apakah forward ke client app sukses atau gagal, dengan opsi \*Retry\* manual jika callback proyek tujuan sempat \*down\*).



\### 5.5 Dokumentasi API Pengguna (Developer-Facing)

\* Sistem harus memiliki dokumentasi API yang dapat dipakai oleh Internal Developer sebagai pengguna layanan `payment.naeva.id`.

\* Dokumentasi ini minimal mencakup:

&#x20;   \* tujuan endpoint dan kapan endpoint dipakai

&#x20;   \* base URL production dan sandbox/testing jika dibutuhkan

&#x20;   \* skema autentikasi antar-server yang berlaku (`X-App-ID`, HMAC signature, timestamp, dan fallback legacy sementara jika masih diizinkan)

&#x20;   \* contoh header request lengkap

&#x20;   \* contoh body request JSON

&#x20;   \* contoh response sukses dan response error

&#x20;   \* daftar status transaksi yang mungkin diterima client app

&#x20;   \* contract payload callback/webhook forwarding ke project asal

&#x20;   \* langkah integrasi end-to-end dari create charge sampai menerima callback status pembayaran

\* Pada fase awal, dokumentasi boleh disiapkan sebagai \*living document\* di folder `docs/` sebelum nantinya dipublikasikan ke format yang lebih ramah pengguna (misalnya halaman docs internal, Postman collection, atau OpenAPI/Swagger).

\* Deliverable dokumentasi awal yang direncanakan:

&#x20;   \* `docs/API.md` untuk panduan penggunaan API

&#x20;   \* contoh request siap pakai untuk `charge`, `project profile`, `transaction lookup`, dan callback verification

&#x20;   \* contoh snippet integrasi untuk client app internal



\---



\## 6. Non-Functional Requirements



\### 6.1 Security

\* Komunikasi wajib menggunakan HTTPS.

\* API `payment.naeva.id` yang diakses oleh Client Apps wajib menggunakan autentikasi Bearer Token (kombinasi `app\_id` dan `secret\_key`) atau signature berbasis HMAC.

\* Kredensial Midtrans (Server Key) wajib disimpan dengan aman di environment variable (.env) dan terenkripsi.

\* Dokumentasi API tidak boleh membuka secret aktual, tetapi harus menjelaskan format header, pembentukan signature, dan contoh request dummy yang aman untuk dibagikan.



\### 6.2 Availability \& Reliability

\* Sistem harus memiliki mekanisme \*retry log\* untuk webhook forwarding. Jika proyek tujuan sedang \*down\*, sistem akan mencoba mengirim ulang webhook sebanyak 3 kali dengan jeda waktu tertentu (Exponential Backoff).

\* Skalabilitas database yang baik karena akan menampung histori transaksi dari seluruh proyek Naeva.



\---



\## 7. API Specification (Draft Singkat)

Bagian ini adalah draft spesifikasi teknis awal. Pada tahap berikutnya perlu diturunkan menjadi dokumentasi pengguna yang lebih lengkap, terstruktur, dan siap dipakai oleh tim integrasi.

Rencana isi dokumentasi pengguna tersebut:

\* ringkasan alur integrasi

\* autentikasi request dengan contoh header lengkap

\* contoh request/response per endpoint

\* daftar error code dan penyebab umum

\* contoh payload callback dari `payment.naeva.id` ke project asal

\* contoh skenario retry dan idempotensi



\### 1. Inisiasi Pembayaran (Client App -> Payment Service)

\* \*\*Method:\*\* `POST`

\* \*\*Endpoint:\*\* `https://payment.naeva.id/api/v1/charge`

\* \*\*Headers:\*\*

&#x20;   \* `X-App-ID: project\_a\_prod`

&#x20;   \* `X-Timestamp: 1760000000`

&#x20;   \* `X-Payment-Signature: <hmac-sha256-signature>`

&#x20;   \* `X-Secret-Key: secret\_abc123...` (\*legacy fallback sementara selama masa migrasi, bukan target akhir\*)

\* \*\*Request Body:\*\*

&#x20;   ```json

&#x20;   {

&#x20;     "order\_id": "INV-PROJECTA-2026-001",

&#x20;     "gross\_amount": 150000,

&#x20;     "customer\_details": {

&#x20;       "first\_name": "Budi",

&#x20;       "email": "budi@example.com"

&#x20;     },

&#x20;     "custom\_callback\_url": "\[https://project-a.id/api/payment/notification](https://project-a.id/api/payment/notification)"

&#x20;   }

&#x20;   ```

\* \*\*Response Body (Success 201):\*\*

&#x20;   ```json

&#x20;   {

&#x20;     "status": "success",

&#x20;     "token": "snap-token-xyz-789",

&#x20;     "redirect\_url": "\[https://app.midtrans.com/snap/v2/vtweb/snap-token-xyz-789](https://app.midtrans.com/snap/v2/vtweb/snap-token-xyz-789)"

&#x20;   }

&#x20;   ```



\### 2. Forwarding Notification ke Client App (Payment Service -> Client App)

\* \*\*Method:\*\* `POST`

\* \*\*Endpoint:\*\* Ditentukan oleh `custom\_callback\_url` atau `default\_callback\_url` proyek.

\* \*\*Payload yang diteruskan:\*\*

&#x20;   ```json

&#x20;   {

&#x20;     "order\_id": "INV-PROJECTA-2026-001",

&#x20;     "transaction\_status": "settlement",

&#x20;     "payment\_type": "bank\_transfer",

&#x20;     "gross\_amount": 150000,

&#x20;     "transaction\_time": "2026-06-20 02:30:00"

&#x20;   }

&#x20;   ```

\* Dokumentasi pengguna final nantinya juga harus menjelaskan:

&#x20;   \* header yang ikut dikirim saat callback forwarding

&#x20;   \* cara verifikasi signature callback dari `payment.naeva.id`

&#x20;   \* ekspektasi HTTP status code dari client app

&#x20;   \* perilaku retry jika endpoint client app gagal merespons



\---



\## 8. Data Model (Rencana Tabel Database)



\### Tabel `projects`

\* `id` (INT, PK)

\* `app\_id` (VARCHAR, Unique)

\* `secret\_key` (VARCHAR)

\* `project\_name` (VARCHAR)

\* `default\_callback\_url` (TEXT)



\### Tabel `transactions`

\* `id` (BIGINT, PK)

\* `project\_id` (INT, FK to projects)

\* `client\_order\_id` (VARCHAR) -> ID order asli dari proyek Anda

\* `midtrans\_transaction\_id` (VARCHAR, Nullable)

\* `amount` (DECIMAL)

\* `status` (VARCHAR) -> pending/settlement/expire/failed

\* `callback\_status` (VARCHAR) -> pending/success/failed (status forward ke proyek tujuan)

\* `created\_at` \& `updated\_at`



\---



\## 9. Timeline \& Key Milestones

1\.  \*\*Fase 1: Setup \& Integrasi Midtrans Core\*\* (Membangun core API untuk membuat token Snap dan menerima webhook dari Midtrans).

2\.  \*\*Fase 2: Multi-Tenant \& Forwarding System\*\* (Membuat logika routing webhook berdasarkan `project\_id` ke proyek asal).

3\.  \*\*Fase 3: Deployment \& Integrasi Pilot Project\*\* (Deploy ke `payment.naeva.id` dan mencoba menghubungkan 1 proyek pertama Anda sebagai uji coba).

4\.  \*\*Fase 4: Dokumentasi Integrasi Developer\*\* (Menyusun dokumentasi API pengguna yang lengkap di `docs/API.md`, termasuk auth, contoh header, request, response, error, dan callback contract).

5\.  \*\*Fase 5: Rollout\*\* (Menghubungkan proyek-proyek lainnya dengan acuan dokumentasi yang sudah stabil).

