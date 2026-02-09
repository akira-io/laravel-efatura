# [Feature]: Implement Middleware Communication Client

### Problem to Solve
There is currently no mechanism to communicate with the fiscal middleware for submission and status checks. The package is "Middleware-first" by design, so this missing component renders it unusable for its intended purpose.

### Proposed Solution
Develop a client that uses the configured credentials to authenticate and send XML payloads to the middleware endpoints. It should handle submission requests and process the middleware responses, normalizing errors as per the design principles.
