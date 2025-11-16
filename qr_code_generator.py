"""
=======================================================
QR CODE GENERATOR FOR LIBRARY READING SYSTEM
Library Hub of Tambo, Lipa City
=======================================================
This script generates QR codes for all books in the database
"""

import qrcode
import os

# =======================================================
# CONFIGURATION
# =======================================================
OUTPUT_FOLDER = "qr_codes"
QR_SIZE = 10  # Size of QR code boxes
QR_BORDER = 4  # Border size

# Create directory for QR codes if it doesn't exist
os.makedirs(OUTPUT_FOLDER, exist_ok=True)

# =======================================================
# BOOK DATABASE - Primary Keys (QR Codes)
# =======================================================
books_data = [
    {
        "qr_code": "QR001",
        "book_id": "1",
        "title": "The Adventures of Tom Sawyer",
        "author": "Mark Twain"
    },
    {
        "qr_code": "QR002",
        "book_id": "2",
        "title": "Charlotte's Web",
        "author": "E.B. White"
    },
    {
        "qr_code": "QR003",
        "book_id": "3",
        "title": "Where the Red Fern Grows",
        "author": "Wilson Rawls"
    },
    {
        "qr_code": "QR004",
        "book_id": "4",
        "title": "The Lion, the Witch and the Wardrobe",
        "author": "C.S. Lewis"
    },
    {
        "qr_code": "QR005",
        "book_id": "5",
        "title": "Bridge to Terabithia",
        "author": "Katherine Paterson"
    },
    {
        "qr_code": "QR006",
        "book_id": "6",
        "title": "Harry Potter and the Sorcerer's Stone",
        "author": "J.K. Rowling"
    },
    {
        "qr_code": "QR007",
        "book_id": "7",
        "title": "The Secret Garden",
        "author": "Frances Hodgson Burnett"
    },
    {
        "qr_code": "QR008",
        "book_id": "8",
        "title": "Matilda",
        "author": "Roald Dahl"
    },
    {
        "qr_code": "QR009",
        "book_id": "9",
        "title": "Percy Jackson: The Lightning Thief",
        "author": "Rick Riordan"
    },
    {
        "qr_code": "QR010",
        "book_id": "10",
        "title": "Wonder",
        "author": "R.J. Palacio"
    }
]

# =======================================================
# FUNCTION: Generate QR Code
# =======================================================
def generate_qr_code(data, filename):
    """
    Generate a QR code image
    
    Args:
        data (str): Data to encode in QR code
        filename (str): Name of output file
    """
    # Create QR code instance
    qr = qrcode.QRCode(
        version=1,  # Controls size (1 is smallest)
        error_correction=qrcode.constants.ERROR_CORRECT_H,  # High error correction
        box_size=QR_SIZE,
        border=QR_BORDER,
    )
    
    # Add data to QR code
    qr.add_data(data)
    qr.make(fit=True)
    
    # Create image
    img = qr.make_image(fill_color="black", back_color="white")
    
    # Save image
    img.save(filename)
    
    print(f"✅ Generated: {filename}")

# =======================================================
# FUNCTION: Generate All QR Codes
# =======================================================
def generate_all_qr_codes():
    """
    Generate QR codes for all books in the database
    """
    print("📚 Library Hub QR Code Generator")
    print("=" * 50)
    print(f"Generating QR codes for {len(books_data)} books...\n")

    # Generate QR code for each book
    for book in books_data:
        qr_code = book['qr_code']
        title = book['title']
        author = book['author']
        
        # Generate QR code with just the QR code identifier
        filename = f"{OUTPUT_FOLDER}/{qr_code}.png"
        generate_qr_code(qr_code, filename)
        
        print(f"   Book: {title}")
        print(f"   Author: {author}")
        print(f"   QR Code: {qr_code}\n")

    print("=" * 50)
    print(f"✅ Successfully generated {len(books_data)} QR codes!")
    print(f"📁 QR codes saved in: {OUTPUT_FOLDER}/")
    print("\n📋 QR Code List:")
    for book in books_data:
        print(f"   • {book['qr_code']} - {book['title']}")

# =======================================================
# FUNCTION: Generate SQL Update Statements
# =======================================================
def generate_sql_updates():
    """
    Generate SQL UPDATE statements for database
    """
    print("\n" + "=" * 60)
    print("SQL UPDATE STATEMENTS")
    print("=" * 60)
    print("\n-- Copy and paste these into your MySQL database:\n")
    
    for book in books_data:
        qr_code = book["qr_code"]
        book_id = book["book_id"]
        qr_path = f"qr_codes/{qr_code}.png"
        
        sql = f"UPDATE books SET qr_code_path = '{qr_path}' WHERE book_id = {book_id};"
        print(sql)
    
    print("\n" + "=" * 60)

# =======================================================
# FUNCTION: Generate HTML Display Code
# =======================================================
def generate_html_display():
    """
    Generate HTML code to display QR codes
    """
    print("\n" + "=" * 60)
    print("HTML DISPLAY CODE")
    print("=" * 60)
    print("\n<!-- Copy this HTML to display QR codes -->\n")
    
    html = '<div class="qr-codes-container">\n'
    
    for book in books_data:
        qr_code = book["qr_code"]
        title = book["title"]
        author = book["author"]
        
        html += f'''    <div class="qr-card">
        <img src="qr_codes/{qr_code}.png" alt="{title} QR Code">
        <h3>{title}</h3>
        <p>by {author}</p>
        <p><strong>QR Code: {qr_code}</strong></p>
    </div>
'''
    
    html += '</div>'
    print(html)
    print("\n" + "=" * 60)

# =================================================
# MAIN EXECUTION
# =======================================================
if __name__ == "__main__":
    # Generate all QR codes
    generate_all_qr_codes()
    
    # Generate SQL update statements
    generate_sql_updates()
    
    # Generate HTML display code
    generate_html_display()
    
    print("\n✅ All tasks completed successfully!")
    print(f"📁 QR codes saved in: {os.path.abspath(OUTPUT_FOLDER)}")
    print("\n" + "=" * 60)


# =======================================================
# ADDITIONAL UTILITY FUNCTIONS
# =======================================================

def generate_single_qr(qr_code_text, title="Custom"):
    """
    Generate a single QR code (utility function)
    
    Usage:
        generate_single_qr("QR006", "New Book Title")
    """
    os.makedirs(OUTPUT_FOLDER, exist_ok=True)
    filename = f"{qr_code_text}.png"
    file_path = generate_qr_code(qr_code_text, filename)
    print(f"✅ Generated single QR code: {file_path}")
    return file_path

def generate_qr_batch(qr_codes_list):
    """
    Generate QR codes from a list
    
    Usage:
        generate_qr_batch(["QR006", "QR007", "QR008"])
    """
    os.makedirs(OUTPUT_FOLDER, exist_ok=True)
    for qr_code in qr_codes_list:
        filename = f"{qr_code}.png"
        file_path = generate_qr_code(qr_code, filename)
        print(f"✅ Generated: {file_path}")
    print(f"\n✅ Batch complete - {len(qr_codes_list)} codes generated")


# =======================================================
# INSTRUCTIONS FOR USE
# =======================================================
"""
HOW TO USE THIS SCRIPT:

1. INSTALL REQUIRED PACKAGE:
   pip install qrcode[pil]

2. RUN THE SCRIPT:
   python qr_code_generator.py

3. OUTPUT:
   - Creates 'qr_codes' folder
   - Generates QR001.png through QR005.png
   - Displays SQL UPDATE statements
   - Shows HTML display code

4. INTEGRATE WITH DATABASE:
   - Copy SQL statements from output
   - Run them in your MySQL database
   - QR code paths will be stored

5. DISPLAY IN WEB APP:
   - Copy generated QR code images to your web server
   - Use HTML code provided to display them
   - Or use: <img src="qr_codes/QR001.png" alt="Book QR">

6. ADDING NEW BOOKS:
   - Add new entry to books_data list
   - Run script again
   - Update database with new SQL statements

EXAMPLE - Add a new book:
    {
        "qr_code": "QR006",
        "book_id": "6",
        "title": "Your New Book",
        "author": "Author Name"
    }
"""