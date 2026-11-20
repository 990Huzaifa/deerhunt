# Bot Creation — Frontend Implementation Guide

Admin dashboard se bots background process me generate hote hain. Avatar upload **required nahi**. Bot count CSV/TXT me kitne full names hain us se aata hai.

---

## Auth

Sab bot admin APIs ke liye:

1. Admin login: `POST /api/admin/signin`
2. Token header me bhejo:

```http
Authorization: Bearer {admin_token}
Accept: application/json
```

Routes `auth:sanctum` + `admin` middleware ke under hain:  
`/api/admin/...`

---

## Flow (UI)

```
1. Admin form open kare
2. Daily Like Limit + Daily Comment Limit enter kare
3. Full names wali CSV/TXT file select kare
4. Submit → API turant success return karti hai (job queue pe chali jati hai)
5. Bots list refresh / poll karke naye bots dikhao
```

> Generation **async** hai. Success response ka matlab bots abhi DB me complete nahi — background worker unhe create karega.

---

## Create Bots

### Endpoint

```http
POST /api/admin/generate-bots
Content-Type: multipart/form-data
Authorization: Bearer {admin_token}
```

### Form fields

| Field | Type | Required | Rules | Description |
|--------|------|----------|--------|-------------|
| `names` | file | Yes | `.csv` / `.txt`, max **5MB** | Full names list |
| `daily_like_limit` | number | Yes | integer, `0–1000` | Per-bot daily like limit |
| `daily_comment_limit` | number | Yes | integer, `0–1000` | Per-bot daily comment limit |

### CSV / TXT format

**Option A — with header**

```csv
full_name
John Hunter
Mike Woods
Sarah Trail
```

Accepted headers (first row, case-insensitive):  
`name`, `full_name`, `fullname`, `bot_name`

**Option B — no header (one name per line)**

```txt
John Hunter
Mike Woods
Sarah Trail
```

Notes for FE:

- Pehli column use hoti hai (agar multi-column CSV ho).
- Empty lines skip.
- Duplicate names unique kar diye jate hain.
- **Bot count = unique valid names** (max **500** per request).
- Avatar **mat** bhejo — bots bina avatar ke banenge (`avatar = null`).

### Example (fetch)

```js
const formData = new FormData();
formData.append('names', fileInput.files[0]); // File object
formData.append('daily_like_limit', '20');
formData.append('daily_comment_limit', '10');

const res = await fetch('/api/admin/generate-bots', {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${adminToken}`,
    Accept: 'application/json',
  },
  body: formData,
});

const data = await res.json();
```

### Success response (`200`)

```json
{
  "status": "success",
  "message": "Bot generation started in background for 3 bots.",
  "bot_count": 3,
  "config": {
    "daily_like_limit": 20,
    "daily_comment_limit": 10
  }
}
```

### Error responses

| Status | Example body | When |
|--------|----------------|------|
| `422` | `{ "error": "Please upload a CSV/TXT file with full names" }` | Validation fail |
| `422` | `{ "error": "No valid full names found in the uploaded file" }` | Empty / invalid file |
| `422` | `{ "error": "Maximum 500 bots can be generated per upload" }` | Too many names |
| `401` | `{ "message": "Unauthorized Admin" }` | Missing/invalid admin token |
| `500` | `{ "error": "..." }` | Server error |

FE tip: `error` string ko toast/snackbar me show karo.

---

## What backend does after submit

For each name in the file (background job):

1. Uses uploaded **full_name**
2. Generates **username** + **bio** (OpenAI)
3. Creates user with `is_bot = true`, **no avatar**
4. Creates `bot_config` with given daily like/comment limits

UI ko progress webhook nahi milta — bots list se status dekho.

---

## List Bots (refresh after create)

```http
GET /api/admin/bots?per_page=15
Authorization: Bearer {admin_token}
```

Paginated response of bots (`is_bot = true`) with `botConfig`.

Suggested UX after generate success:

- Toast: “Bot generation started for N bots”
- 5–10s baad bots list reload / poll
- Optional: “Generation running in background” banner

---

## Bot Detail

```http
GET /api/admin/bots/{id}
Authorization: Bearer {admin_token}
```

Returns bot + activities.

---

## Max Bot Create (optional helper)

```http
GET /api/admin/max-bot-create
Authorization: Bearer {admin_token}
```

```json
{
  "max_bot_create": 500,
  "avatar_required": false,
  "message": "Upload a CSV of full names; bot count = number of names (max 500)."
}
```

Avatar pool limit **hat gaya**. Soft cap = **500** names/request.

---

## Deprecated / not needed for new flow

| Old behavior | New behavior |
|--------------|--------------|
| `bot_count` number field | Count = CSV names |
| Avatar upload required before create | No avatar required |
| `upload-bot-avatars` for generation | Not needed for create flow |
| Sync wait for all bots | Async job; immediate API response |

`POST /api/admin/upload-bot-avatars` ab generation ke liye zaroori nahi.

---

## Recommended Admin UI fields

```
┌─────────────────────────────────────────┐
│  Generate Bots                          │
│                                         │
│  Daily Like Limit      [ 20        ]    │
│  Daily Comment Limit   [ 10        ]    │
│                                         │
│  Full Names (CSV/TXT)  [ Choose file ]  │
│  Detected bots: 3                       │  ← optional client-side preview
│                                         │
│  [ Generate Bots ]                      │
└─────────────────────────────────────────┘
```

Optional client-side preview:

1. File select pe CSV parse karo (browser me)
2. Unique non-empty names count dikhao as “Detected bots: N”
3. Agar `N > 500` → submit disable + error

---

## Sample CSV for QA

Save as `bots.csv`:

```csv
full_name
Alex Ridge
Jordan Pike
Casey Boone
```

Then call generate-bots with:

- `daily_like_limit=15`
- `daily_comment_limit=5`
- `names=<bots.csv>`

Expected: `bot_count: 3`, message generation started, phir `/api/admin/bots` pe 3 naye bots (avatar null).
