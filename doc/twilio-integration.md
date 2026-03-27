# Twilio SMS Integration for OTP

The plugin uses Twilio to send OTP (One-Time Password) via SMS when users click "Send OTP" in the Activate Device flow.

## Flow

1. User enters phone number in the Activate Device modal.
2. User clicks "Send OTP".
3. **Backend checks** `wp_nwp_devices` table for a matching phone number.
4. If **not found** → Error: "This phone number is not registered. Please register your device first."
5. If **found** → Generate 6-digit OTP, store in transient (10 min expiry), send SMS via Twilio.
6. User receives SMS with verification code.

## Setup

### 1. Create Twilio Account

1. Go to [twilio.com](https://www.twilio.com) and sign up.
2. From the [Twilio Console](https://console.twilio.com), note:
   - **Account SID**
   - **Auth Token**
3. Purchase a **Phone Number** (or use a trial number for testing):
   - Go to Phone Numbers → Manage → Buy a number
   - Choose a number with SMS capability
   - Format: E.164 (e.g. `+15551234567`)

### 2. Configure in WordPress

1. Go to **Settings → NWP Gateway** in the WordPress admin.
2. Enter:
   - **Account SID** — from Twilio console
   - **Auth Token** — from Twilio console
   - **From** — your Twilio phone number in E.164 format (`+15551234567`)
3. Save.

### 3. E.164 Format

- **US/Canada:** `+1` + 10 digits = `+15551234567`
- The form masks input as `+1 (555) 123-4567`; the backend normalizes to `+15551234567` before sending to Twilio.

## Phone Number Matching

The plugin matches stored phone numbers flexibly. The `wp_nwp_devices.phone` column may store:

- `+1 (555) 123-4567`
- `+15551234567`
- `5551234567`

The backend strips non-digits and compares the 10-digit portion before sending OTP.

## OTP Storage

- OTP is stored in a WordPress transient: `cpm_nwp_otp_{md5(phone_e164)}`
- Expiry: 10 minutes
- Value: `{ otp: "123456", created: timestamp }`
- Used for verification (next step: OTP verification handler).

## SMS geographic permissions (Nepal +977 and other countries)

If Twilio returns an error like **“Permission to send an SMS has not been enabled for the region indicated by the ‘To’ number”** (often HTTP **400**, code **21408**), your Twilio account is blocking **outbound SMS to that country**.

1. Log in to the [Twilio Console](https://console.twilio.com).
2. Open **Messaging** → **Settings** → **SMS geographic permissions** (wording may vary slightly; you can search the console for **“Geo permissions”** or **“SMS geographic”**).
3. Find **Nepal** (or the relevant country) and **enable** outbound SMS for that destination.
4. Save, then try **Send OTP** again.

Until that country is enabled, SMS to `+977…` numbers will fail even if the rest of the integration is correct.

## Twilio Trial Account

- Trial accounts can only send SMS to **verified** phone numbers.
- Add/test numbers at: Twilio Console → Phone Numbers → Manage → Verified Caller IDs.
- Production: upgrade your Twilio account to send to any number.

## Security

- Auth Token is stored in `wp_options`; restrict admin access.
- Consider using environment variables or `wp-config.php` constants for production:
  ```php
  define( 'CPM_NWP_TWILIO_SID', 'AC...' );
  define( 'CPM_NWP_TWILIO_TOKEN', '...' );
  define( 'CPM_NWP_TWILIO_FROM', '+15551234567' );
  ```
- The OTP service can be extended to read from these constants if defined.

## Next Step: OTP Verification

Currently the plugin sends the OTP. The next step is to add an OTP verification form/step that:
1. Accepts the 6-digit code from the user
2. Compares with stored transient
3. Marks the device as "activated" or logs the user in
