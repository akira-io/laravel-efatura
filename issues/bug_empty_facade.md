# [Bug]: Main entry point `Efatura` class is empty

### What happened?
The `Akira\Efatura\Efatura` class is defined but contains no methods or properties. It provides no public API for the package's functionality, contrary to typical package design patterns where the main class acts as a facade or entry point.

### How to reproduce the bug
1. Attempt to use the `Efatura` class in code.
2. Observe that it has no methods available.

### Package Version
dev-main

### PHP Version
8.2.x

### Laravel Version
10.x

### Which operating systems does this happen with?
Linux

### Notes
The `Efatura` class should expose methods or forward calls to underlying services (like XML generation or IUD calculation) to provide a unified API for the user.
