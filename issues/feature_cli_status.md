# [Feature]: Add CLI Command for Middleware Status Check

### Problem to Solve
Users have no quick way to verify connectivity to the middleware from the command line. Troubleshooting connection issues or verifying credentials currently requires manual API calls or custom scripts.

### Proposed Solution
Add an artisan command (e.g., `efatura:status`) that pings the middleware health or status endpoint using the configured credentials. The command should output the connection status and environment details (e.g., Sandbox vs Production) to the console.
