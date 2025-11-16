import cv2
import numpy as np
from pyzbar.pyzbar import decode

def scan_qr_code():
    # Initialize the video capture
    cap = cv2.VideoCapture(0)

    while True:
        # Capture frame-by-frame
        ret, frame = cap.read()
        if not ret:
            break

        # Decode the QR codes in the frame
        decoded_objects = decode(frame)

        for obj in decoded_objects:
            # Draw a rectangle around the detected QR code
            points = obj.polygon
            if len(points) == 4:  # Ensure it's a quadrilateral
                cv2.polylines(frame, [np.array(points)], isClosed=True, color=(0, 255, 0), thickness=2)

            # Get the QR code data
            qr_data = obj.data.decode('utf-8')
            print(f'Detected QR Code: {qr_data}')

            # Optionally, you can break the loop after detecting a QR code
            break

        # Display the resulting frame
        cv2.imshow('QR Code Scanner', frame)

        # Break the loop on 'q' key press
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break

    # Release the capture and close windows
    cap.release()
    cv2.destroyAllWindows()