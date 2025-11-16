"""
ESP32-CAM Virtual Camera for Chrome
Creates a virtual webcam that streams from ESP32-CAM
"""

import cv2
import numpy as np
import pyvirtualcam
import requests
from threading import Thread
import time

# =======================================================
# ESP32-CAM CONFIGURATION
# =======================================================
ESP32_CAM_URL = "http://192.168.1.100"  # ⚠️ CHANGE THIS to your ESP32-CAM IP
STREAM_URL = f"{ESP32_CAM_URL}:81/stream"  # Default ESP32-CAM stream port

# Virtual camera settings
CAMERA_WIDTH = 640
CAMERA_HEIGHT = 480
CAMERA_FPS = 30

# =======================================================
# STREAM READER CLASS
# =======================================================
class ESP32CamReader:
    def __init__(self, stream_url):
        self.stream_url = stream_url
        self.frame = None
        self.running = False
        self.thread = None
        
    def start(self):
        """Start reading stream in background thread"""
        self.running = True
        self.thread = Thread(target=self._read_stream, daemon=True)
        self.thread.start()
        return self
    
    def _read_stream(self):
        """Read MJPEG stream from ESP32-CAM"""
        while self.running:
            try:
                # Open stream
                response = requests.get(self.stream_url, stream=True, timeout=5)
                bytes_data = bytes()
                
                # Read MJPEG stream
                for chunk in response.iter_content(chunk_size=1024):
                    bytes_data += chunk
                    
                    # Find JPEG boundaries
                    a = bytes_data.find(b'\xff\xd8')  # JPEG start
                    b = bytes_data.find(b'\xff\xd9')  # JPEG end
                    
                    if a != -1 and b != -1:
                        jpg = bytes_data[a:b+2]
                        bytes_data = bytes_data[b+2:]
                        
                        # Decode JPEG to frame
                        frame = cv2.imdecode(
                            np.frombuffer(jpg, dtype=np.uint8),
                            cv2.IMREAD_COLOR
                        )
                        
                        if frame is not None:
                            # Resize to virtual camera resolution
                            self.frame = cv2.resize(frame, (CAMERA_WIDTH, CAMERA_HEIGHT))
                            
            except Exception as e:
                print(f"❌ Stream error: {e}")
                time.sleep(2)  # Wait before reconnecting
                
    def read(self):
        """Get latest frame"""
        return self.frame
    
    def stop(self):
        """Stop reading stream"""
        self.running = False
        if self.thread:
            self.thread.join()

# =======================================================
# VIRTUAL CAMERA SETUP
# =======================================================
def create_virtual_camera():
    """Create virtual camera and stream ESP32-CAM feed"""
    
    print("\n" + "=" * 60)
    print("📹 ESP32-CAM VIRTUAL CAMERA")
    print("=" * 60)
    print(f"ESP32-CAM URL: {ESP32_CAM_URL}")
    print(f"Stream URL: {STREAM_URL}")
    print(f"Resolution: {CAMERA_WIDTH}x{CAMERA_HEIGHT}")
    print(f"FPS: {CAMERA_FPS}")
    print("=" * 60 + "\n")
    
    # Test connection
    print("🔍 Testing ESP32-CAM connection...")
    try:
        response = requests.get(ESP32_CAM_URL, timeout=5)
        print("✅ ESP32-CAM connected successfully!\n")
    except Exception as e:
        print(f"❌ Cannot connect to ESP32-CAM: {e}")
        print("⚠️ Make sure ESP32-CAM is powered on and URL is correct")
        return
    
    # Start stream reader
    print("📡 Starting ESP32-CAM stream reader...")
    reader = ESP32CamReader(STREAM_URL)
    reader.start()
    
    # Wait for first frame
    print("⏳ Waiting for first frame...")
    while reader.read() is None:
        time.sleep(0.1)
    print("✅ Receiving frames!\n")
    
    # Create virtual camera
    print("🎥 Creating virtual camera...")
    with pyvirtualcam.Camera(width=CAMERA_WIDTH, height=CAMERA_HEIGHT, fps=CAMERA_FPS) as cam:
        print(f"✅ Virtual camera created: {cam.device}")
        print(f"📌 Camera name: ESP32-CAM Virtual Camera")
        print("\n" + "=" * 60)
        print("🚀 VIRTUAL CAMERA IS RUNNING!")
        print("=" * 60)
        print("📷 You can now select this camera in:")
        print("   • Google Chrome")
        print("   • Zoom")
        print("   • Microsoft Teams")
        print("   • OBS Studio")
        print("   • Any video app")
        print("\n⌨️  Press Ctrl+C to stop")
        print("=" * 60 + "\n")
        
        frame_count = 0
        start_time = time.time()
        
        try:
            while True:
                # Get frame from ESP32-CAM
                frame = reader.read()
                
                if frame is not None:
                    # Convert BGR to RGB (required for virtual camera)
                    frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                    
                    # Send to virtual camera
                    cam.send(frame_rgb)
                    
                    # FPS counter
                    frame_count += 1
                    if frame_count % 30 == 0:
                        elapsed = time.time() - start_time
                        fps = frame_count / elapsed
                        print(f"📊 FPS: {fps:.1f} | Frames: {frame_count}")
                    
                    # Sleep to maintain FPS
                    cam.sleep_until_next_frame()
                else:
                    # Show placeholder if no frame
                    placeholder = np.zeros((CAMERA_HEIGHT, CAMERA_WIDTH, 3), dtype=np.uint8)
                    cv2.putText(placeholder, "Waiting for ESP32-CAM...", 
                              (50, CAMERA_HEIGHT//2), 
                              cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
                    cam.send(placeholder)
                    cam.sleep_until_next_frame()
                    
        except KeyboardInterrupt:
            print("\n\n⏹️  Stopping virtual camera...")
            reader.stop()
            print("✅ Virtual camera stopped")

# =======================================================
# MAIN EXECUTION
# =======================================================
if __name__ == "__main__":
    try:
        create_virtual_camera()
    except Exception as e:
        print(f"\n❌ Error: {e}")
        import traceback
        traceback.print_exc()
