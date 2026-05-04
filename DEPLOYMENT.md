# Elkris Foods DFAS - Deployment Guide

## Overview
Deploying the new Laravel/Filament application to VPS at `/www/wwwroot/dfasdemo.elkrisfoods.com`

## Prerequisites (Verify in aaPanel)
- [ ] PHP 8.3 or 8.4 installed
- [ ] MySQL/MariaDB installed
- [ ] Node.js 20+ installed (for building assets)
- [ ] Composer installed

## PHP Extensions Required (App Store → PHP → Install extensions)
- fileinfo
- intl
- openssl
- mbstring
- xml
- curl
- zip
- gmp
- bcmath
- pdo_mysql
- redis (optional but recommended)

---

## Phase 1: Backup Old Application

1. **In aaPanel → Files**:
   - Navigate to `/www/wwwroot/dfasdemo.elkrisfoods.com`
   - Select ALL files and folders
   - Right-click → **Compress** → create `backup-old-app.zip`
   - Download this backup to your computer

2. **In aaPanel → Database**:
   - Find your current database
   - Click **Backup** → create a backup
   - Download the backup

---

## Phase 2: Upload New Code via aaPanel

1. **Create deployment ZIP locally**:
   - Open terminal in your project folder
   - Run:
   ```powershell
   # Exclude vendor and node_modules (we'll install fresh on VPS)
   $exclude = @('vendor', 'node_modules', '.env', '.git', 'storage', 'bootstrap/cache/*', '.env.local')
   Compress-Archive -Path (Get-ChildItem -Exclude $exclude) -DestinationPath "C:\Users\Onoja\Documents\Elkris\Projects\elkrisfoods-deploy.zip"
   ```

2. **In aaPanel → Files**:
   - Navigate to `/www/wwwroot/`
   - Rename old directory: `dfasdemo.elkrisfoods.com` → `dfasdemo.elkrisfoods.com.old`
   - Create new directory: `dfasdemo.elkrisfoods.com`
   - Enter the new directory
   - **Upload** `elkrisfoods-deploy.zip`
   - Right-click → **Extract** → extract all files
   - Delete the ZIP after extraction

---

## Phase 3: SSH Setup Commands

SSH into your VPS:
```bash
ssh root@YOUR_VPS_IP
```

Run these commands:
```bash
# Navigate to project
cd /www/wwwroot/dfasdemo.elkrisfoods.com

# Create .env file
cp .env.production.example .env

# Edit .env with your actual database credentials
nano .env
# Update DB_DATABASE, DB_USERNAME, DB_PASSWORD
# Update OLD_DB_USERNAME, OLD_DB_PASSWORD
# Set DEEPSEEK_API_KEY if using Copilot

# Install PHP dependencies (production mode)
composer install --no-dev --optimize-autoloader

# Install Node dependencies and build assets
npm install
npm run build

# Generate application key
php artisan key:generate --force

# Set correct ownership
chown -R www:www /www/wwwroot/dfasdemo.elkrisfoods.com

# Set permissions
chmod -R 775 storage bootstrap/cache
chmod -R 775 public/storage

# Run migrations
php artisan migrate --force

# Migrate old data (users + customers from old app)
php artisan app:migrate-old-data --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:upgrade
php artisan optimize
```

---

## Phase 4: aaPanel Website Configuration

1. **In aaPanel → Website**:
   - Click on `dfasdemo.elkrisfoods.com` → **Settings**
   - **Site directory**: Set to `/www/wwwroot/dfasdemo.elkrisfoods.com/public`
   - **PHP version**: Select PHP 8.3 or 8.4
   - **PHP-FPM**: Ensure it's running

2. **In aaPanel → Website → Settings → Configuration** (nginx):
   Ensure your config has:
   ```nginx
   root /www/wwwroot/dfasdemo.elkrisfoods.com/public;
   
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

3. **SSL Setup**:
   - Go to Website → `dfasdemo.elkrisfoods.com` → **SSL**
   - Select **Let's Encrypt** → **Apply**
   - Enable **Force HTTPS**

---

## Phase 5: Queue Worker (Optional but Recommended)

If you want background job processing:

1. **Change queue driver**:
   ```bash
   # Edit .env
   nano /www/wwwroot/dfasdemo.elkrisfoods.com/.env
   # Change: QUEUE_CONNECTION=database
   
   # Create queue table
   cd /www/wwwroot/dfasdemo.elkrisfoods.com
   php artisan queue:table
   php artisan migrate --force
   ```

2. **In aaPanel → App Store → Install Supervisor**

3. **In aaPanel → Supervisor → Add daemon**:
   - **Name**: `laravel-queue`
   - **Run user**: `www`
   - **Run directory**: `/www/wwwroot/dfasdemo.elkrisfoods.com`
   - **Command**: `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`
   - **Processes**: `2`

---

## Phase 6: Cron Job for Scheduler

**In aaPanel → Cron → Add task**:
- **Type**: Shell script
- **Execution cycle**: Every minute (`* * * * *`)
- **Script content**:
```bash
php /www/wwwroot/dfasdemo.elkrisfoods.com/artisan schedule:run >> /dev/null 2>&1
```

---

## Phase 7: Verify

```bash
# Check health
curl https://dfasdemo.elkrisfoods.com/up

# Check counts
php artisan tinker --execute 'echo "Users: ".DB::table("users")->count()."\nCustomers: ".DB::table("customers")->count();'

# View logs
tail -f /www/wwwroot/dfasdemo.elkrisfoods.com/storage/logs/laravel.log
```

---

## Troubleshooting

**"500 Internal Server Error"**:
```bash
cd /www/wwwroot/dfasdemo.elkrisfoods.com
php artisan optimize:clear
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

**"Vite manifest not found"**:
```bash
cd /www/wwwroot/dfasdemo.elkrisfoods.com
npm run build
```

**Permission denied**:
```bash
chown -R www:www /www/wwwroot/dfasdemo.elkrisfoods.com
chmod -R 775 storage bootstrap/cache
```

**Database connection error**:
```bash
# Test connection
php artisan tinker --execute 'echo DB::connection()->getDatabaseName();'
```
