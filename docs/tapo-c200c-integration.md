# การเชื่อมต่อกล้อง TP-Link Tapo C200C กับ Mykid

> กล้องที่โรงเรียนใช้จริง: **TP-Link Tapo C200C × 5 ตัว**
> อ่านคู่กับ [cctv-architecture.md](cctv-architecture.md)

## 1. Tapo C200C

- กล้อง IP ในบ้าน/อาคาร ความละเอียด Full HD ส่งภาพผ่าน Wi-Fi หรือ LAN
- รองรับ **RTSP** และ **ONVIF** เมื่อสร้าง "Camera Account" ในแอป Tapo
- ใน Mykid เก็บเป็นข้อมูลธรรมดา ไม่ได้ผูกระบบกับยี่ห้อ ถ้าเปลี่ยนเป็น Hikvision / Dahua / กล้อง RTSP อื่น เปลี่ยนแค่ข้อมูล:
  - `vendor = 'TP-Link'`
  - `camera_model = 'Tapo C200C'`

## 2. เปิด RTSP บนกล้อง

1. แอป Tapo → เลือกกล้อง → ⚙️ ตั้งค่า → **Advanced Settings → Camera Account**
2. สร้าง Username/Password สำหรับ RTSP (คนละชุดกับบัญชี TP-Link Cloud) — ตั้งชื่อเช่น `mykid_view`
3. จอง IP ให้กล้องใน Router (DHCP reservation) เพื่อไม่ให้ IP เปลี่ยน
4. RTSP URL ของ Tapo:

   ```
   rtsp://<username>:<password>@<camera-ip>:554/stream1   # main stream คุณภาพสูง (ใช้ตอนดูเต็มจอ)
   rtsp://<username>:<password>@<camera-ip>:554/stream2   # sub stream คุณภาพต่ำ (เหมาะกับ Grid)
   ```

5. ทดสอบในวง LAN ด้วย VLC (Media → Open Network Stream) ก่อนต่อเข้า Media Server

ข้อควรระวัง:
- เมื่อเปิด **Privacy Mode** ในแอป Tapo กล้องจะไม่ส่งภาพ ซึ่ง Mykid ควรแสดงสถานะเป็น Offline หรือ Maintenance
- จำนวนการเชื่อมต่อ RTSP พร้อมกันของกล้องมีจำกัด จึงต้องให้ Media Server ดึงภาพเพียง 1 การเชื่อมต่อแล้วกระจายต่อให้ผู้ชม
- เฟิร์มแวร์ใหม่อาจเปลี่ยนพฤติกรรม RTSP ควรทดสอบซ้ำหลังอัปเดต

## 3. RTSP

RTSP เป็นโปรโตคอลส่งวิดีโอที่กล้องใช้ภายในวง LAN

**Browser ไม่ควรเปิด RTSP โดยตรง:**
- Browser ทุกตัวไม่รองรับ RTSP
- URL มีรหัสผ่านของกล้องอยู่ในตัว
- ต้องเปิด Port กล้องออกอินเทอร์เน็ต

## 4. NVR (ไม่บังคับ)

- ถ้าโรงเรียนต้องการบันทึกภาพ ให้ใช้ microSD ในกล้อง หรือ NVR/Frigate แยกต่างหาก
- **Mykid ไม่บันทึกภาพ** หน้าที่ของ Mykid คือดู Realtime ตามสิทธิ์เท่านั้น
- NVR หลายรุ่นส่งต่อ RTSP ได้ จึงใช้ NVR เป็นต้นทางของ Media Server แทนกล้องได้

## 5. Media Server (แนะนำ MediaMTX)

ติดตั้งบนเครื่องในโรงเรียน (Mini PC / NAS / Server) ในวง LAN เดียวกับกล้อง:

```yaml
# mediamtx.yml — Credential ของกล้องอยู่ที่นี่ที่เดียว ไม่เข้า Mykid / Browser
paths:
  phonsoong-cam01: { source: rtsp://mykid_view:<รหัสผ่าน>@192.168.10.21:554/stream2, sourceOnDemand: yes }
  phonsoong-cam02: { source: rtsp://mykid_view:<รหัสผ่าน>@192.168.10.22:554/stream2, sourceOnDemand: yes }
  phonsoong-cam03: { source: rtsp://mykid_view:<รหัสผ่าน>@192.168.10.23:554/stream2, sourceOnDemand: yes }
  phonsoong-cam04: { source: rtsp://mykid_view:<รหัสผ่าน>@192.168.10.24:554/stream2, sourceOnDemand: yes }
  phonsoong-cam05: { source: rtsp://mykid_view:<รหัสผ่าน>@192.168.10.25:554/stream2, sourceOnDemand: yes }

# ให้ Mykid เป็นผู้ตัดสินว่าใครดูได้ (สร้าง endpoint นี้ใน Production)
authMethod: http
authHTTPAddress: https://mykid.example.ac.th/api/media-auth
```

- `sourceOnDemand` ทำให้ดึงภาพจากกล้องเฉพาะตอนมีคนดู
- ทางเลือกอื่น: go2rtc (รองรับ Tapo ได้ดี), Janus, Wowza, Ant Media

## 6. HLS

- MediaMTX สร้าง HLS ให้อัตโนมัติ (ค่าเริ่มต้น port 8888):
  `https://media.example.ac.th/phonsoong-cam01/index.m3u8` (ผ่าน reverse proxy HTTPS)
- ใน Mykid ตั้งค่า: `stream_type = hls`, `stream_url = URL ด้านบน`
- Mykid ต่อท้าย `?token=...` อายุสั้นให้เอง และ Media Server ตรวจ token กับ Mykid ก่อนส่งภาพ

## 7. WebRTC

- MediaMTX ให้ WHEP endpoint (ค่าเริ่มต้น port 8889):
  `https://media.example.ac.th/phonsoong-cam01/whep`
- ใน Mykid ตั้งค่า: `stream_type = webrtc`, `stream_url = URL ด้านบน`
- Player ส่ง `Authorization: Bearer <token>` ไปกับ SDP offer
- ผู้ปกครองที่ใช้ 4G / อยู่หลัง NAT ต้องมี TURN server (เช่น coturn)

## 8. Browser / Mykid

```
Tapo C200C ──RTSP──▶ MediaMTX ──HLS / WebRTC──▶ Mykid (ตรวจสิทธิ์ + token) ──▶ Browser
```

**Mykid Frontend ได้รับเพียง:**
- HLS URL (+ token อายุสั้น) หรือ
- WebRTC (WHEP) URL (+ token)

**สิ่งที่ห้ามอยู่ใน HTML / JavaScript / URL ที่ส่งให้ Browser:**
- Username / Password ของกล้อง
- RTSP URL
- IP ภายในของกล้อง

Mykid บังคับเรื่องนี้แล้วดังนี้:
- `rtsp_url` เป็นช่องแบบเขียนอย่างเดียว ไม่ถูกส่งกลับไปที่หน้าเว็บ
- ระบบปฏิเสธ RTSP URL ที่มี `user:pass@`
- หน้าเว็บไม่มี Stream URL — Player ขอผ่าน API `camera_stream` ที่ตรวจสิทธิ์ทุกครั้ง

## 9. ตัวอย่างการตั้งค่ากล้องใน Mykid (โรงเรียนอนุบาลโพนสูง)

| รหัส | ชื่อ | ห้อง | stream_type | stream_url (Production) |
|---|---|---|---|---|
| CAM-01 | กล้องหน้าห้องอนุบาล 1 | อนุบาล 1 | hls | `https://media.example.ac.th/phonsoong-cam01/index.m3u8` |
| CAM-02 | กล้องหน้าห้องอนุบาล 2 | อนุบาล 2 | hls | `.../phonsoong-cam02/index.m3u8` |
| CAM-03 | กล้องหน้าห้องอนุบาล 3 | อนุบาล 3 | hls | `.../phonsoong-cam03/index.m3u8` |
| CAM-04 | กล้องสนามเด็กเล่น | ส่วนกลาง | webrtc | `.../phonsoong-cam04/whep` |
| CAM-05 | กล้องทางเข้าโรงเรียน | ส่วนกลาง | hls | `.../phonsoong-cam05/index.m3u8` |

ตอนนี้ (Demo) ทุกตัวเป็น `stream_type = mock`, `stream_url = ''` เปลี่ยนได้ทีละตัวผ่านหน้า **Admin → กล้องวงจรปิด → จัดการกล้อง** โดยไม่ต้องแก้ UI
