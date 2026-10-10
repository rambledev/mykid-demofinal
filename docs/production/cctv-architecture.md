# CCTV Architecture (Production)

> Demo reference: [../cctv-architecture.md](../cctv-architecture.md), [../tapo-c200c-integration.md](../tapo-c200c-integration.md)
> Scope ปัจจุบัน: 2 โรงเรียน × 5 กล้อง Tapo C200C = **10 กล้อง** (DB รองรับมากกว่านี้โดยไม่ต้องเปลี่ยนโครงสร้าง)

## 1. หลักการ
1. **CodeIgniter ไม่แตะ video stream** — ถือแค่ metadata, สิทธิ์, การออก token, UI
2. **RTSP ไม่ออกนอกเครือข่าย Media Server** — browser ไม่เคยเห็น RTSP URL หรือรหัสผ่านกล้อง
3. **Token อายุสั้นต่อ user ต่อกล้อง** — ตรวจสิทธิ์ทุกครั้งที่ขอดู
4. Media Server เป็น component แยก (deploy, scale, restart ได้อิสระจากแอป)

## 2. Data Flow

```
 ┌──────── School LAN ────────┐            ┌──────────── Media Server ────────────┐           ┌──── Browser ────┐
 │ Tapo C200C ×5              │  RTSP      │ MediaMTX (หรือเทียบเท่า)                │  HLS /    │ <video> + hls.js │
 │ rtsp://user:pass@cam/stream1├──────────► │ path: s1-cam01 → rtsp source (secret) │  WebRTC   │ หรือ WHEP client  │
 │ (account ของกล้องใน Tapo app)│ (on-demand)│ remux H.264 → HLS (LL-HLS) / WebRTC   ├─────────► │                  │
 └─────────────────────────────┘            │ auth hook ────────────┐               │ +token    └────────▲─────────┘
                                            └───────────────────────┼───────────────┘                    │
                                                                    ▼                                    │
                                   CodeIgniter 4  /internal/media-auth (shared secret)                   │
                                   POST /api/v1/cameras/{id}/stream-sessions ─────────── token + URL ────┘
```

| ฝั่ง | ทำอะไร | ไม่ทำอะไร |
|---|---|---|
| **CodeIgniter** | CRUD กล้อง (ชื่อ, ตำแหน่ง, ห้อง, `stream_path`, สถานะ, เปิด/ปิด), `camera_permissions`, ตรวจสิทธิ์ผู้ดู, ออก stream token, audit `VIEW_CAMERA`, UI ผู้เล่น | เก็บ RTSP/รหัสผ่านกล้อง, transcode, proxy วิดีโอ |
| **Media Server** | ดึง RTSP เมื่อมีคนดู (on-demand), remux/transcode, เสิร์ฟ HLS/WebRTC, เรียก auth hook ทุกการเชื่อมต่อ, รายงาน path ready/offline | ตัดสินสิทธิ์เอง (ถามแอปเสมอ) |
| **กล้อง** | RTSP stream1 (HD) / stream2 (SD) ใน LAN | ออกอินเทอร์เน็ตตรง |

## 3. Network Placement (PENDING BUSINESS CONFIRMATION — P6 Media Server Location)

กล้องอยู่หลัง NAT ของโรงเรียน ต้องเลือกวิธีให้ Media Server เข้าถึง RTSP:

| ทางเลือก | อธิบาย | ข้อดี | ข้อเสีย |
|---|---|---|---|
| **A. Edge Media Server ที่โรงเรียน** (เสนอ) | mini PC ต่อ LAN โรงเรียน รัน Media Server; ออกสู่อินเทอร์เน็ตผ่าน outbound tunnel (เช่น Cloudflare Tunnel / WireGuard ไป cloud relay) | RTSP อยู่ใน LAN 100%, ใช้ upload เฉพาะตอนมีคนดู, ไม่ต้องเปิด port | ต้องมีอุปกรณ์ + ไฟ/เน็ตที่โรงเรียน, ดูแลรักษาหน้างาน |
| B. Cloud Media Server + VPN site-to-site | Media Server อยู่บน cloud, router โรงเรียนต่อ VPN | อุปกรณ์หน้างานน้อย, จัดการรวมศูนย์ | ต้องใช้ router ที่รองรับ VPN, RTSP วิ่งผ่าน VPN ตลอดเวลาที่ดู, ตั้งค่ายากกว่า |
| C. Port forward RTSP ออกอินเทอร์เน็ต | — | ง่าย | **ห้ามใช้** — เปิดกล้องสู่สาธารณะ |

Bandwidth (ต่อโรงเรียน): stream2 SD ~0.5–1 Mbps/กล้อง/ผู้ชม (ผ่าน relay ตัวเดียว ผู้ชมหลายคนใช้ต้นทางเดียว); HD ~2–3 Mbps
→ ค่าเริ่มต้นเสนอให้ผู้ปกครองดู SD, Admin เลือก HD ได้

## 4. Stream Token

| รายการ | ค่า |
|---|---|
| Endpoint | `POST /api/v1/cameras/{id}/stream-sessions` (session auth + CSRF) |
| ตรวจ | `CameraPolicy::view(ctx, camera)` — role + school + ห้อง (`camera_permissions`) + `is_active` + status |
| Token | HMAC-SHA256(`CCTV_STREAM_TOKEN_SECRET`, `stream_path | user_id | exp`) หรือ JWT (HS256) อายุ **5 นาที** |
| Response | `{ "protocol": "hls", "url": "https://media.<domain>/s1-cam01/index.m3u8", "token": "…", "expires_at": "…" }` |
| Media Server ตรวจ | auth hook → `POST /internal/media-auth` (shared secret `CCTV_AUTH_HOOK_SECRET`, เข้าได้จาก network ภายในเท่านั้น) ตรวจลายเซ็น + หมดอายุ + path ตรงกัน |
| ต่ออายุ | client ขอ token ใหม่ก่อนหมด (ผ่าน policy ใหม่ทุกครั้ง → ถ้าถูกถอดสิทธิ์ ภาพตัดภายใน ≤ 5 นาที) |
| Rate limit | 30 ครั้ง/นาที/user |
| Audit | `VIEW_CAMERA` ทุกครั้งที่ออก token (user, camera_code, school) |

## 5. Data Model
ดู [database.md §11](database.md#11-cctv) — `cameras` (ไม่มีคอลัมน์ RTSP/credential), `camera_permissions` (role + ห้อง)

Mapping `stream_path → rtsp://…` อยู่ใน config ของ Media Server (ไฟล์ config + env secret ของ Media Server stack)
— การเพิ่มกล้อง = Admin เพิ่ม metadata ในแอป + ผู้ดูแลระบบเพิ่ม path ใน Media Server (Runbook ใน Phase ที่ทำ CCTV)

## 6. Permission Rules (ยกจาก Demo ที่ทดสอบแล้ว)

| Role | เห็นกล้อง |
|---|---|
| Super Admin | ทุกกล้องทุกโรงเรียน (รวม inactive เพื่อจัดการ) |
| School Admin | ทุกกล้องในโรงเรียนตน, จัดการได้ |
| Executive | ทุกกล้องในโรงเรียนตน (ดูอย่างเดียว) |
| Teacher | กล้องห้องที่ตนสอน + กล้องส่วนกลางที่ `camera_permissions` อนุญาต; เฉพาะ `is_active` |
| Parent | กล้องห้องของลูก (ถ้าอนุญาต) + ส่วนกลางที่อนุญาต; เฉพาะ `is_active` |

## 7. Status & Monitoring
- NOW: Admin ตั้งสถานะ online/offline/maintenance เอง + ผู้เล่นแสดง "กล้องออฟไลน์" เมื่อ stream ไม่ ready
- FUTURE: command `cctv:sync-status` ถาม API ของ Media Server ทุก 1 นาที → อัปเดต `cameras.status/last_status_at`

## 8. Security Checklist
- [x] RTSP URL / รหัสผ่านกล้อง ไม่อยู่ใน DB แอป, frontend, log, API response
- [x] Media Server เสิร์ฟผ่าน HTTPS, ไม่มี anonymous read
- [x] Token ผูก path + user + หมดอายุ 5 นาที
- [x] Auth hook endpoint ไม่เปิดสู่สาธารณะ + shared secret
- [x] บัญชีกล้อง (Tapo camera account) ใช้รหัสผ่านเฉพาะ ไม่ซ้ำกับบัญชีอื่น, firmware อัปเดต
- [x] CSP อนุญาตเฉพาะ origin ของ Media Server (`media-src`, `connect-src`)

## 9. NOW vs FUTURE

| NOW | FUTURE |
|---|---|
| Live view HLS (+WebRTC ถ้า network รองรับ) | Recording / playback ย้อนหลัง (ต้องมีนโยบาย retention วิดีโอแยก) |
| 10 กล้อง, Media Server 1 ตัว/โรงเรียน (edge) หรือ 1 ตัวรวม | หลาย media node + load balancing |
| สถานะตั้งเอง | Health sync อัตโนมัติ + แจ้งเตือนกล้องดับ |
| SD สำหรับผู้ปกครอง | Adaptive bitrate (ต้อง transcode → ใช้ CPU) |
