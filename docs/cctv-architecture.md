# Mykid CCTV — สถาปัตยกรรมระบบดูกล้องแบบ Realtime

> เอกสารสำหรับทีมพัฒนาที่จะนำ CCTV ของ Mykid (Package A / B) ไปเชื่อมกล้องจริง
> สถานะปัจจุบัน: **Demo** — กล้องทุกตัวเป็น `stream_type = mock` ยังไม่ได้เชื่อมกล้องจริง

## 1. ภาพรวม

```
┌──────────────┐  RTSP   ┌───────────────────────┐  HLS / WebRTC  ┌──────────────────┐  HTTPS  ┌──────────┐
│ IP Camera    │ ──────▶ │ NVR / Media Server    │ ─────────────▶ │ Mykid Web App    │ ──────▶ │ Browser  │
│ (Tapo C200C) │         │ (MediaMTX / go2rtc)   │                │ (สิทธิ์ + Token) │         │          │
└──────────────┘         └───────────────────────┘                └──────────────────┘         └──────────┘
   วง LAN โรงเรียน          ถือ Credential กล้อง                  ไม่ใช่ Video Server         ได้แค่ HLS/WebRTC
```

**ห้ามทำ:** `Camera RTSP ──▶ Browser` โดยตรง

- Browser ทุกตัวไม่รองรับ RTSP — `<video src="rtsp://...">` ใช้งานไม่ได้
- RTSP URL มี Username/Password ของกล้อง ถ้าส่งให้ Browser เท่ากับเปิดเผยรหัสกล้อง
- ต้องเปิด Port กล้องออกอินเทอร์เน็ต ซึ่งเสี่ยงมาก

## 2. องค์ประกอบ

| # | Component | หน้าที่ | ตัวอย่าง |
|---|---|---|---|
| 1 | **Camera** | ส่งภาพเป็น RTSP ภายในวง LAN | TP-Link Tapo C200C, Hikvision, Dahua, กล้อง RTSP/ONVIF ทั่วไป |
| 2 | **NVR** (ไม่บังคับ) | บันทึกภาพของโรงเรียน (Mykid ไม่บันทึก) | NVR ของยี่ห้อกล้อง, Frigate |
| 3 | **Media Server** | ดึง RTSP จากกล้อง **ครั้งเดียว** แล้วแปลงและกระจายเป็น HLS/WebRTC, ตรวจ Token ก่อนให้ดู | **MediaMTX** (แนะนำ), go2rtc, Janus, Wowza, Ant Media |
| 4 | **HLS** | วิดีโอแบบไฟล์ `.m3u8` ผ่าน HTTPS เล่นได้ทุก Browser (ผ่าน hls.js) | หน่วงประมาณ 2–8 วินาที (LL-HLS ประมาณ 2 วินาที) |
| 5 | **WebRTC** | วิดีโอหน่วงต่ำผ่านโปรโตคอล WHEP | หน่วงน้อยกว่า 1 วินาที, ต้องมี TURN เมื่ออยู่หลัง NAT |
| 6 | **Browser** | เล่น HLS/WebRTC ที่ได้จาก Mykid หลังผ่านการตรวจสิทธิ์ | `core/assets/js/core.js` (CCTV Player) |

## 3. Mykid ทำอะไร / ไม่ทำอะไร

**ทำ:**
- เก็บข้อมูลกล้อง (`cameras`) และสิทธิ์ (`cameraPermissions`) แยกตาม `school_id`
- ตัดสินว่า **ใคร ดูกล้องไหนได้** ด้วย `can_view_camera()` ใน `core/cctv.php` เป็นจุดเดียว
- ออก Playback Descriptor `{ type, url, token, expiresAt }` ผ่าน API `camera_stream` หลังตรวจสิทธิ์

**ไม่ทำ:**
- ไม่เป็น Video Streaming Server
- ไม่บันทึก ดาวน์โหลด หรือย้อนดูภาพ (Feature ปัจจุบันคือ "ดู Realtime" เท่านั้น)
- ไม่ส่ง `rtsp_url` หรือ Credential ใด ๆ ไปที่ Browser

## 4. ข้อมูลกล้อง (cameras)

| Field | ความหมาย |
|---|---|
| `id`, `code` | รหัสกล้อง เช่น `CAM-01` |
| `school_id` | โรงเรียนเจ้าของกล้อง (แยกข้อมูล Multi-School) |
| `classroom_id` | ห้องที่กล้องติดตั้ง — `null` = พื้นที่ส่วนกลาง |
| `name`, `location`, `description` | ข้อมูลแสดงผล |
| `vendor`, `camera_model` | ยี่ห้อ/รุ่น (เป็นข้อมูลธรรมดา เปลี่ยนยี่ห้อได้โดยไม่แก้ระบบ) |
| `stream_type` | `mock` / `hls` / `webrtc` |
| `stream_url` | URL ที่ Media Server ให้ (HLS `.m3u8` หรือ WebRTC WHEP) |
| `rtsp_url` | **ฝั่ง Server เท่านั้น** — ระบบปฏิเสธ URL ที่มี `user:pass@` |
| `status` | `online` / `offline` / `maintenance` |
| `is_active` | เปิด/ปิดการใช้งาน |

ไฟล์ที่เกี่ยวข้อง:

- `pack-x/includes/cctv-data.php`: กล้องของแต่ละโรงเรียน
- `pack-x/includes/cctv-permissions.php`: สิทธิ์เริ่มต้น
- `core/seed.php`: `cctv_school_cameras()` / `seed_cameras()`

## 5. สิทธิ์ (Role → School → Classroom → Camera Permission)

| Role | กล้องที่เห็น |
|---|---|
| Super Admin | ทุกกล้อง ทุกโรงเรียน (จัดการได้) |
| Admin | ทุกกล้องในโรงเรียนตัวเอง (จัดการได้) |
| Executive | ทุกกล้องในโรงเรียนตัวเอง (อ่านอย่างเดียว) |
| Teacher | กล้องห้องตัวเอง + กล้องส่วนกลางที่มีแถวใน `cameraPermissions` |
| Parent | กล้องห้องของลูก + กล้องส่วนกลางที่มีแถวใน `cameraPermissions` (ไม่ได้ทุกกล้องอัตโนมัติ) |

- Scope ทั้งหมดมาจาก Session (`current_user()`) ไม่รับ `?school_id=` / `?camera_id=` จาก URL
- หน้าเว็บไม่มี Stream URL — Player ขอผ่าน API ซึ่งเรียก `authorized_camera()` ทุกครั้ง

## 6. Player (Frontend)

`core/assets/js/core.js` → `CCTV` มี driver 3 แบบ ใช้ interface เดียวกัน:

| `stream_type` | การทำงาน |
|---|---|
| `mock` | ภาพการ์ตูน SVG เคลื่อนไหว + เวลาแบบ CCTV (ใช้เมื่อ `stream_url` ว่าง → ไม่มีวัน Error) |
| `hls` | Safari/iOS เล่น HLS ได้เอง; Browser อื่นโหลด `hls.js` **เฉพาะตอนมี URL จริง** |
| `webrtc` | WHEP: ส่ง SDP offer ไป `stream_url` พร้อม `Authorization: Bearer <token>` แล้วเล่น answer |

ถ้า `stream_url` ว่าง ระบบจะใช้ `mock` อัตโนมัติ (ดู `camera_stream_descriptor()`) — จึงเปลี่ยนเป็นกล้องจริงได้ทีละตัวโดยไม่ต้องแก้ UI

## 7. ขั้นตอนนำ Stream จริงมาใส่

1. ติดตั้ง Media Server (แนะนำ MediaMTX) ในวง LAN เดียวกับกล้อง
2. ตั้งค่า path ต่อกล้อง (Credential อยู่ใน config ของ Media Server เท่านั้น):

   ```yaml
   # mediamtx.yml
   paths:
     school1-cam01:
       source: rtsp://mykid_view:<รหัสผ่าน>@192.168.10.21:554/stream2
       sourceOnDemand: yes
   ```

3. เปิดผ่าน HTTPS (reverse proxy เช่น Nginx/Caddy):
   - HLS: `https://media.example.ac.th/school1-cam01/index.m3u8`
   - WebRTC (WHEP): `https://media.example.ac.th/school1-cam01/whep`
4. เข้า Mykid ด้วยบัญชี Admin → **กล้องวงจรปิด → จัดการกล้อง → แก้ไข**
   - ประเภท Stream = `HLS` หรือ `WebRTC`
   - Stream URL = URL จากข้อ 3
   - RTSP URL (ไม่บังคับ) = ใช้อ้างอิงฝั่ง Server เท่านั้น **ไม่มีรหัสผ่าน**
5. ให้ Media Server ตรวจ Token กับ Mykid ก่อนส่งภาพ (MediaMTX รองรับ external HTTP auth):

   ```yaml
   authMethod: http
   authHTTPAddress: https://mykid.example.ac.th/api/media-auth   # ต้องสร้าง endpoint นี้ใน Production
   ```

   `media-auth` ต้องตรวจ token (HMAC: camera id + user id + expiry) และเรียก `can_view_camera()` ซ้ำ

## 8. Checklist ก่อนขึ้น Production

- [ ] กล้องอยู่ VLAN แยก ไม่เปิด Port RTSP (554) ออกอินเทอร์เน็ต
- [ ] Media Server ให้บริการผ่าน HTTPS เท่านั้น
- [ ] เปลี่ยน key ของ token จาก session-based (Demo) เป็น secret ฝั่ง Server, อายุ token ≤ 5 นาที
- [ ] สร้าง `/api/media-auth` ให้ Media Server ตรวจสิทธิ์ทุกครั้งที่เริ่มดู
- [ ] WebRTC: ตั้ง TURN server (เช่น coturn) สำหรับผู้ใช้หลัง NAT/4G
- [ ] ใช้ sub-stream (ความละเอียดต่ำ) ใน Grid และ main-stream เมื่อดูเต็มจอ เพื่อลด Bandwidth
- [ ] บันทึก Log การเปิดดูกล้อง (มี `mk_log('CCTV', 'stream requested', ...)` อยู่แล้ว → ย้ายลง Database)
- [ ] PDPA: ภาพเด็กเป็นข้อมูลส่วนบุคคล — มีประกาศความเป็นส่วนตัว, ความยินยอมผู้ปกครอง, ป้ายแจ้งมีกล้อง
- [ ] กำหนดนโยบายว่ากล้องส่วนกลางตัวไหนให้ผู้ปกครองดูได้ (ค่าเริ่มต้น: สนามเด็กเล่นได้, ทางเข้าโรงเรียนไม่ได้)
