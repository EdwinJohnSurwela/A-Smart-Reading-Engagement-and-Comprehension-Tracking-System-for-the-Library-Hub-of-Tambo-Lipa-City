import cv2
import qrcode

def generate_qr_code(data, filename):
    """Generate a QR code and save it as an image file."""
    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_L,
        box_size=10,
        border=4,
    )
    qr.add_data(data)
    qr.make(fit=True)

    img = qr.make_image(fill_color="black", back_color="white")
    img.save(filename)

def display_qr_code(filename):
    """Display the generated QR code image using OpenCV."""
    img = cv2.imread(filename)
    cv2.imshow("QR Code", img)
    cv2.waitKey(0)
    cv2.destroyAllWindows()