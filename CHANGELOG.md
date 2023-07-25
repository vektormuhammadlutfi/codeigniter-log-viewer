# CHANGELOG

## V1.1.2

- Fix issue [#13](https://github.com/SeunMatt/codeigniter-log-viewer/issues/13) and improve regex patterns

## V1.1.1

- Fix security bug with file download [#8](https://github.com/SeunMatt/codeigniter-log-viewer/issues/8)
- Updated required PHP version to >=7.1

## V1.1.0

- Added API capability, such that log and log files can be obtained in a JSON response
- Added log folder path and log file pattern configuration such that they can be configured via CodeIgniter's config.php file

## V1.1.6

- Refactor Code For Simple Understanding
- The code structure has been preserved and aligned properly.
- Detailed comments have been added to each function, explaining their purpose, parameters, and return values.
- The code now adheres to the PSR-2 coding standard for better readability and maintainability.
- Repeated code has been removed (e.g., duplicate `showLogs()` function).
- Proper error handling and validation should be implemented to handle unexpected scenarios and improve the code's robustness.
- Consider using dependency injection to decouple the code from framework-specific dependencies.
- Add exception handling and appropriate error messages for better error reporting and debugging.
- Input validation and sanitization should be implemented to prevent potential security vulnerabilities.
- Unit tests should be written to ensure the reliability and correctness of the code.
