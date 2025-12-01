# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP application that scrapes Instagram posts using the Apify API (`apify/instagram-post-scraper` actor) and sends the extracted data to Google Sheets.

The app runs via **cronjob** and automatically:
1. Reads Instagram URLs from Google Sheets (hoja "Urls")
2. Scrapes post metrics using Apify
3. Downloads and uploads post images to FTP server
4. Saves results to Google Sheets (hoja "Posts")
5. Intelligently updates existing rows when URLs are reprocessed on the same day

## Development Commands

### Running the Cronjob Script

The application is designed to run automatically via cronjob:

```bash
# Execute manually for testing
php cron-process.php

# Or make it executable and run
chmod +x cron-process.php
./cron-process.php
```

See `CRONJOB-SETUP.md` for complete cronjob configuration instructions.

### Local Development (without Docker)

```bash
# Install dependencies
composer install

# Start local PHP server
php -S localhost:8000

# Access the application
# http://localhost:8000
```

### Docker Development (Recommended)

```bash
# Windows setup
setup.bat
docker-compose up -d

# Linux/Mac setup
chmod +x setup.sh
./setup.sh
docker-compose up -d

# Access the application
# http://localhost:8080

# View logs
docker-compose logs -f

# Stop containers
docker-compose down

# Rebuild
docker-compose build --no-cache
```

### Cloud Run Deployment

```bash
# Deploy to Google Cloud Run
chmod +x deploy-cloudrun.sh
./deploy-cloudrun.sh
```

## Configuration Requirements

### API Credentials Setup

The application requires three sets of credentials configured in `config/config.php`:

1. **Apify API Token** (`APIFY_API_TOKEN`): Used to call the Instagram scraper actor
2. **Google Sheets Credentials** (`GOOGLE_CREDENTIALS_PATH`, `GOOGLE_SHEET_ID`, `GOOGLE_SHEET_RANGE`): Service account JSON file for Google Sheets API access
3. **FTP Credentials** (`FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`): For uploading post images to FTP server

**Important**: Never commit `config/config.php` or `config/google-credentials.json` to version control. Use the example files as templates.

## Architecture

### Request Flow

1. **cron-process.php** - Main entry point for automated execution:
   - Reads URLs from Google Sheets (hoja "Urls", columna A)
   - Validates Instagram URL format
   - Calls Apify actor with URL list
   - Processes results and saves to Google Sheets

2. **index.php** - (Legacy) Web interface - no longer actively used for cronjob workflow

3. **process.php** - Processing pipeline (also used by cron-process.php):
   - Validates and parses uploaded CSV file
   - Extracts Instagram URLs using regex pattern: `/instagram\.com\/(p|reel)\/[A-Za-z0-9_-]+/`
   - Calls Apify actor via `callApifyActor()` function
   - Polls for results using `waitForApifyResults()` (max 5 minutes, 5-second intervals)
   - Retrieves dataset via `getApifyDataset()`
   - Transforms data into flat array structure
   - Sends to Google Sheets via `GoogleSheetsHelper`

3. **test-google-sheets.php** - Test endpoint that bypasses Apify:
   - Reads pre-saved Apify response from `respuesta.json`
   - Processes data identically to `process.php`
   - Sends to Google Sheets
   - Useful for testing Sheets integration without API costs

### Key Components

**GoogleSheetsHelper.php**
- Wrapper class for Google Sheets API v4
- `appendData()`: Appends rows to sheet, automatically adds headers if missing
- `checkIfHasHeaders()`: Checks if row 1 contains headers to avoid duplicates
- `findUrlRow()`: Searches for an existing Instagram URL in the sheet, returns row index, date, and image URL
- `isSameDay()`: Compares if a date is from the current day
- `updateRow()`: Updates an existing row with new data
- `getData()`: Reads data from sheet
- `clearSheet()`: Clears sheet range

**FTPHelper.php**
- Wrapper class for FTP operations
- `connect()`: Establishes FTP connection with passive mode enabled
- `uploadFile()`: Uploads a local file to FTP
- `uploadImageFromUrl()`: Downloads an image from URL and uploads it to FTP's `posts/` directory
- Returns public URL: `https://losdemarketing.com/posts/{filename}`

### Data Structure

Extracted fields from Apify response:
- `fecha` - Timestamp when data was processed
- `inputUrl` - Original Instagram post URL
- `caption` - Post text/caption
- `ownerUsername` - Instagram username who posted
- `commentsCount` - Number of comments
- `videoViewCount` - Video view count
- `videoPlayCount` - Video play count
- `imageUrl` - Public URL of uploaded image on FTP server

These map to columns A-H in Google Sheets with headers: Fecha, URL, Caption, Usuario, Comentarios, Vistas, Reproducciones, Imagen

### Smart Deduplication & Update Logic

The application implements intelligent URL tracking to avoid duplicates and unnecessary image downloads:

1. **Before processing each post**, it checks if the `inputUrl` already exists in Google Sheets
2. **If URL exists and date is today**:
   - Updates the existing row with fresh metrics
   - Reuses the existing image URL (no FTP download/upload)
3. **If URL exists but date is different**:
   - Creates a new row (allows historical tracking)
   - **Reuses the existing image URL** (no FTP download/upload)
4. **If URL doesn't exist**:
   - Creates new row
   - Downloads image from `displayUrl` field
   - Uploads to FTP at `posts/{shortCode}_{timestamp}.jpg`

This logic prevents:
- Duplicate URLs on the same day
- Unnecessary image downloads - images are downloaded only once per URL
- FTP storage waste from duplicate images
- Each URL's image is downloaded only the first time the URL is processed

### Apify Integration Details

The app uses a polling pattern for async Apify actor execution:
1. POST to `/v2/acts/apify~instagram-post-scraper/runs` starts the actor
2. Poll `/v2/actor-runs/{runId}` every 5 seconds checking for status: SUCCEEDED, FAILED, or ABORTED
3. Once SUCCEEDED, fetch results from `/v2/datasets/{datasetId}/items`
4. Timeout after 60 attempts (5 minutes total)

Note: The Apify payload uses `username` field but actually sends post URLs - this is specific to how the `instagram-post-scraper` actor works.

## Testing

### Test Google Sheets Integration

The `respuesta.json` file contains a pre-saved Apify response. Use this to test the Google Sheets integration without consuming Apify credits:

1. Click "Probar con respuesta.json" button in the UI, or
2. Directly access: `http://localhost:8000/test-google-sheets.php`

This is especially useful when debugging Google Sheets permissions, credential issues, or data formatting.

## Common Issues

### Apify Rate Limits
- The actor may timeout if processing many URLs
- Consider processing in smaller batches for large datasets
- Check Apify account for available credits

### Google Sheets Permissions
- Ensure the service account email (from `google-credentials.json`) has Editor access to the target sheet
- Verify `GOOGLE_SHEET_RANGE` matches an existing sheet tab name
- Check that `GOOGLE_SHEET_ID` is correct (extracted from sheet URL)

## File Security

Files excluded from git (see `.gitignore`):
- `config/config.php` - Contains API keys
- `config/google-credentials.json` - Service account credentials
- `vendor/` - Composer dependencies
- `uploads/` - Temporary CSV uploads (auto-cleaned after processing)
