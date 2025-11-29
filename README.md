# AI Gemini Image Generator

WordPress plugin tạo ảnh nghệ thuật từ ảnh chân dung sử dụng Google Gemini 2.5 Flash Image API.

## Cài đặt

1. Upload thư mục `ai-gemini-image/` vào `/wp-content/plugins/`
2. Kích hoạt plugin trong WordPress Admin
3. Vào **AI Gemini > Settings** nhập API key của Gemini

## Cấu hình

### API Key
Lấy API key tại: https://aistudio.google.com/apikey

### Thanh toán VietQR
Cấu hình trong `inc/payment/vietqr-config.php` hoặc qua WordPress options:
- Mã ngân hàng (MB, VCB, TCB, BIDV, ACB, v.v.)
- Số tài khoản
- Tên chủ tài khoản

### Watermark
Có thể thay đổi text watermark trong AI Gemini > Settings.

## Shortcodes

| Shortcode | Mô tả |
|-----------|-------|
| `[ai_gemini_generator]` | Form tạo ảnh |
| `[ai_gemini_dashboard]` | Dashboard user |
| `[ai_gemini_buy_credit]` | Trang nạp credit |

## Cấu trúc thư mục

```
ai-gemini-image/
├── ai-gemini-image.php       # File main plugin
├── readme.txt                 # WordPress plugin readme
├── info.txt                   # Mô tả dự án chi tiết
│
├── inc/                       # Backend code
│   ├── admin/                 # Admin dashboard
│   ├── api/                   # REST API endpoints
│   ├── credit/                # Hệ thống credit
│   ├── payment/               # Hệ thống thanh toán
│   ├── db/                    # Database management
│   ├── frontend/              # Frontend shortcodes
│   ├── watermark.php          # Xử lý watermark
│   └── helpers.php            # Hàm tiện ích
│
└── assets/                    # Static files (CSS, JS, fonts)
```

## Tính năng

- ✅ Tạo ảnh nghệ thuật từ ảnh chân dung
- ✅ Nhiều style: Anime, Cartoon, Oil Painting, Watercolor, Sketch, Pop Art, Cyberpunk, Fantasy
- ✅ Hệ thống credit cho người dùng
- ✅ Preview có watermark, unlock để tải full
- ✅ Thanh toán VietQR cho thị trường Việt Nam
- ✅ Hỗ trợ guest (không cần đăng nhập)
- ✅ Dashboard admin với thống kê
- ✅ REST API đầy đủ

## Flow hoạt động

1. **Upload** - Người dùng upload ảnh chân dung
2. **Generate** - Chọn style và tạo preview (có watermark)
3. **Unlock** - Trả credit để tải ảnh full chất lượng

## Yêu cầu hệ thống

- WordPress 5.6+
- PHP 7.4+
- MySQL 5.7+
- GD Library (cho xử lý ảnh và watermark)

## Chi tiết kỹ thuật

Xem file `info.txt` để hiểu đầy đủ về cấu trúc và flow hoạt động của plugin.

## License

GPLv2 or later