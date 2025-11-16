"""
QR Code Generation Test Script
Run this to verify Python and qrcode library are working correctly
"""

import sys
import os

print("=" * 60)
print("QR CODE GENERATION TEST")
print("=" * 60)

# Test 1: Check Python version
print(f"\n✓ Python version: {sys.version}")

# Test 2: Check if qrcode library is installed
try:
    import qrcode
    print("✓ qrcode library is installed")
    print(f"  Version: {qrcode.__version__ if hasattr(qrcode, '__version__') else 'Unknown'}")
except ImportError:
    print("✗ ERROR: qrcode library is NOT installed")
    print("\nTo install, run:")
    print("  pip install qrcode[pil]")
    sys.exit(1)

# Test 3: Check PIL/Pillow
try:
    from PIL import Image
    print("✓ PIL/Pillow library is installed")
except ImportError:
    print("✗ ERROR: PIL/Pillow is NOT installed")
    print("\nTo install, run:")
    print("  pip install pillow")
    sys.exit(1)

# Test 4: Generate a test QR code
try:
    print("\n" + "=" * 60)
    print("GENERATING TEST QR CODE")
    print("=" * 60)
    
    test_qr_code = "TEST_QR_001"
    test_output_dir = "qr_codes"
    test_output_path = os.path.join(test_output_dir, "TEST_QR_001.png")
    
    # Create directory if it doesn't exist
    os.makedirs(test_output_dir, exist_ok=True)
    
    # Create QR code
    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_H,
        box_size=10,
        border=4,
    )
    
    qr.add_data(test_qr_code)
    qr.make(fit=True)
    
    img = qr.make_image(fill_color="black", back_color="white")
    img.save(test_output_path)
    
    print(f"✓ Test QR code generated successfully!")
    print(f"  Location: {os.path.abspath(test_output_path)}")
    print(f"  File size: {os.path.getsize(test_output_path)} bytes")
    
    print("\n" + "=" * 60)
    print("ALL TESTS PASSED! ✓")
    print("=" * 60)
    print("\nYour system is ready to generate QR codes!")
    print("You can now add books in the librarian dashboard.")
    
except Exception as e:
    print(f"✗ ERROR generating test QR code: {str(e)}")
    sys.exit(1)
