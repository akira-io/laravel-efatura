# [Feature]: Implement IUD Generation and Validation

### Problem to Solve
The package claims to generate Unique Document Identifier (IUD) values and validate their check digits, but no implementation exists in the codebase. Without IUDs, fiscal documents cannot be uniquely identified or submitted to the authority.

### Proposed Solution
Implement the logic to generate the IUD based on the fiscal data (emitter, document type, serial, sequence, date) and calculate the Luhn check digit as per the technical manual. This should be exposed as a service or helper method.
