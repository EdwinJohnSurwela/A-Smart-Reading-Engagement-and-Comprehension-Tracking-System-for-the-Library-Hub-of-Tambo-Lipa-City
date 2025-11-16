"""
Single QR Code Generator
Generates a single QR code image from command line
Used by PHP to generate QR codes for books
"""

import sys
import qrcode
import os

if len(sys.argv) < 3:
    print("ERROR: Usage: python generate_single_qr.py <qr_code> <output_path>")
    sys.exit(1)

qr_code_text = sys.argv[1]
output_path = sys.argv[2]

try:
    # Create QR code
    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_H,
        box_size=10,
        border=4,
    )

    qr.add_data(qr_code_text)
    qr.make(fit=True)

    img = qr.make_image(fill_color="black", back_color="white")
    
    # Ensure directory exists
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    
    # Save image
    img.save(output_path)

    print(f"SUCCESS: {output_path}")
    sys.exit(0)
except Exception as e:
    print(f"ERROR: {str(e)}")
    sys.exit(1)
