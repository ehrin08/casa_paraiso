# Google Authentication Setup

Casa Paraiso uses verified Google OAuth as the only public customer registration and provisioning path. No public email/password registration is available. After provisioning, a customer may reconfirm the linked Google identity to create a password and use verified email/password login. Pre-authorized staff and admin accounts retain email/password login plus password-setup and reset access.

## Google Cloud configuration

1. Create or select a Google Cloud project and configure the OAuth consent screen.
2. Create an OAuth 2.0 Web application client.
3. Add `http://localhost:8001/auth/google/callback` for local sign-in.
4. Add `http://localhost:8001/profile/delete/google/callback` for local account-deletion confirmation.
5. Add the equivalent HTTPS callback URLs for the final Hostinger domain.
6. Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` in the environment. Never commit the secret.

## Access rules

- `SUPER_ADMIN_EMAIL` must remain `ehrinjohn08@gmail.com` unless ownership is intentionally transferred through a reviewed deployment change.
- First sign-in with an unknown verified Google email creates a customer account.
- A verified Google email that already belongs to a pre-authorized role retains that role instead of creating a duplicate customer.
- The protected super administrator pre-authorizes staff emails through **Admin → Team**, where their operational profile and eligible services are created with the account, and admin emails through **Admin → User access**. Their password setup and recovery are separate from public customer sign-in.
- A customer may add a conventional password only after reauthenticating the linked Google identity; this setup does not create a new account.
- The protected super admin cannot be demoted, deactivated, renamed to another email, or deleted through the application.

## Recovery and security

Access to `ehrinjohn08@gmail.com` is operationally critical. Enable Google two-step verification, maintain current recovery email and phone information, and securely store Google recovery codes. Google Cloud client credentials should be included in the private deployment handover, not in the Git repository.

After deploying configuration changes on Hostinger, clear and rebuild Laravel's production configuration cache.
