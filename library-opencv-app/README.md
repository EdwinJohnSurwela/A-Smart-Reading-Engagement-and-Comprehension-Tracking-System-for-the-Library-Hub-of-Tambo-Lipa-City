# Library OpenCV App

## Overview
This project is a library management system that utilizes OpenCV for QR code scanning and Flask for web application functionality. The application allows users to register, log in, and interact with a library database, including features for managing books and tracking reading progress.

## Setup Instructions

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd library-opencv-app
   ```

2. **Create a virtual environment:**
   ```bash
   python -m venv venv
   source venv/bin/activate  # On Windows use `venv\Scripts\activate`
   ```

3. **Install dependencies:**
   ```bash
   pip install -r requirements.txt
   ```

4. **Set up the database:**
   - Run the SQL commands in `src/database/schema.sql` to create the necessary tables.

5. **Run the application:**
   ```bash
   python src/main.py
   ```

## Usage
- Navigate to `http://localhost:5000` in your web browser to access the application.
- Users can register, log in, and use the QR code scanner to manage books.

## Features
- User authentication (login and signup)
- QR code generation and scanning
- Dashboard for displaying library statistics
- Database integration for storing user and book information

## Dependencies
- OpenCV
- Flask
- qrcode
- Other necessary libraries listed in `requirements.txt`

## Contributing
Contributions are welcome! Please submit a pull request or open an issue for any enhancements or bug fixes.

## License
This project is licensed under the MIT License. See the LICENSE file for details.