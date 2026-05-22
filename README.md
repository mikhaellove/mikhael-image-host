# 🛡️ Project Vault: High-Fidelity Stealth Image Host

A private, security-focused image hosting service acting as a digital vault for high-fidelity images.

## Features

- **Stealth-first design** - No public registration or login forms
- **Database-only storage** - All media stored as BLOBs in MySQL (no filesystem dependency)
- **High-fidelity processing** - 98% quality JPEG with 4:4:4 chroma subsampling, automatic EXIF/GPS stripping
- **Security hardened** - Argon2id, CSRF tokens, session binding, rate limiting
- **Chrooted users** - Complete isolation between user accounts
- **Soft delete** - Prevents slug reuse and accidental exposure
- **API access** - Bearer token authentication for mobile/CLI uploads
- **Chunked uploads** - Support for large files (up to 250MB) via JavaScript chunking

### Multi-Image Galleries

- Upload multiple images as a single gallery entity
- Slot-based storage with per-slot image data and thumbnails encoded as JSON
- Dedicated gallery viewer with navigation between images
- Configurable max images per gallery (default 5, set in admin settings)
- Individual image slots served via `/raw/{slug}/{index}`

### Multimedia Support

- **Video** - Upload and stream video files with a dedicated viewer
- **Audio** - Upload and play audio files with a dedicated viewer
- All media types share the same slug-based URL scheme and share controls

### Image Editing

- Non-destructive rotation and caption updates via the admin editor
- Changes applied on top of the original stored BLOB without re-upload

### Share Controls

- **Expiration dates** - Links automatically become inaccessible after a set date
- **Link passwords** - Protect individual shares with an Argon2id-hashed password
- **Metadata visibility** - Toggle display of upload date, view count, display name, and caption per share

### Landing Page

- Configurable public-facing landing page with a curated gallery display
- Managed entirely from the admin panel

## Requirements

- PHP 8.3+
- MySQL 5.7+ or MariaDB 10.3+
- ImageMagick (`/usr/bin/magick`)
- Apache with mod_rewrite
- Extensions: PDO, pdo_mysql, fileinfo, dom
- Redis (optional) - for per-IP view count deduplication

## Installation

1. Clone this repository to your web directory
2. Set document root to `public/` directory
3. Navigate to `/install` in your browser
4. Follow the installation wizard

**No Composer required** - The application uses a custom PSR-4 autoloader with zero external dependencies.

## Security Notes

- The `/admin` path is not advertised - users must navigate to it manually
- All accounts are invite-only (created by Super-Admin)
- New users must reset their password on first login
- Sessions are bound to User-Agent to prevent hijacking
- All state-changing requests require CSRF tokens
- Login attempts are rate-limited after 5 failures

## API Usage

Generate a personal access token in the admin panel, then use it for uploads:

```bash
curl -X POST https://vault.example.com/api/upload \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -F "image=@photo.jpg" \
  -F "caption=Optional caption"
```

## Storage Management

- Images are automatically converted to JPEG format
- All EXIF/GPS metadata is stripped
- Deleted images have their BLOBs set to NULL to reclaim disk space
- Slugs are permanently reserved (even after deletion) to prevent URL reuse

## License

Private project - All rights reserved.
