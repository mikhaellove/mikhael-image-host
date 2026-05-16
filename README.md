# 🛡️ Project Vault: High-Fidelity Stealth Image Host

A private, security-focused image hosting service acting as a digital vault for high-fidelity images.

## Features

- **Stealth-first design** - No public registration or login forms
- **Database-only storage** - All images stored as BLOBs in MySQL (no filesystem dependency)
- **High-fidelity processing** - 98% quality JPEG with 4:4:4 chroma subsampling
- **Security hardened** - Argon2id, CSRF tokens, session binding, rate limiting
- **Chrooted users** - Complete isolation between user accounts
- **Soft delete** - Prevents slug reuse and accidental exposure
- **API access** - Bearer token authentication for mobile/CLI uploads
- **Chunked uploads** - Support for large files via JavaScript chunking

## Requirements

- PHP 8.3+
- MySQL 5.7+ or MariaDB 10.3+
- ImageMagick (`/usr/bin/magick`)
- Apache with mod_rewrite
- Extensions: PDO, pdo_mysql, fileinfo, dom

## Installation

1. Clone this repository to your web directory
2. Set document root to `public/` directory
3. Navigate to `/install` in your browser
4. Follow the installation wizard

**No Composer required** - The application uses a custom PSR-4 autoloader with zero external dependencies.

## Configuration

After installation, configure your virtual host to point to the `public/` directory:

```apache
<VirtualHost *:443>
    ServerName vault.example.com
    DocumentRoot /path/to/outcolumbus/public

    <Directory /path/to/outcolumbus/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

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
