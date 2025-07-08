## Setup Instructions

### Prerequisites

- PHP 8.0 or higher  
- Composer  
- 

### Installation

1. Clone the repo:

   ```bash
   git clone https://github.com/Sac-tharan/9338ac37-f9ae-46a5-9847-2b195b52bbb3.git
   cd your-laravel-project

2. Install dependencies:

         composer install

3. Copy .env and generate app key
        cp .env.example .env
        php artisan key:generate

4. Run migrations (if applicable)
        php artisan migrate

5. To run your CLI commands
         php artisan report:generate

6. Run all tests using
       php artisan test
