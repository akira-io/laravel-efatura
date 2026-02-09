# [Feature]: Implement XML Generation for Fiscal Documents

### Problem to Solve
The package currently lacks the ability to generate XML documents compliant with the DNRE specification, despite the README claiming it "Builds compliant XML documents". This is a critical limitation as the core purpose of the package is fiscal compliance via XML submission.

### Proposed Solution
Create a service or class responsible for converting `Data` objects into XML strings. The implementation should ensure adherence to the XSD schema required by DNRE. The service should handle the structure and encoding of the XML payload.
