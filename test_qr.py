from qr_code_generator import generate_single_qr, generate_qr_batch

# Test 1: Generate a single QR code
print("Test 1: Generating single QR code...")
generate_single_qr("TEST001", "Test Book")

# Test 2: Generate batch of QR codes
print("\nTest 2: Generating batch of QR codes...")
generate_qr_batch(["QR006", "QR007", "QR008"])

print("\n✅ All tests completed!")
