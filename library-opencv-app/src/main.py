import cv2
from flask import Flask, render_template, request, redirect, url_for
from src.auth.login import login_user
from src.auth.signup import register_user
from src.ui.dashboard import render_dashboard

app = Flask(__name__)

@app.route('/')
def home():
    return render_template('base.html')

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        return login_user(request.form)
    return render_template('login.html')

@app.route('/signup', methods=['GET', 'POST'])
def signup():
    if request.method == 'POST':
        return register_user(request.form)
    return render_template('signup.html')

@app.route('/dashboard')
def dashboard():
    return render_dashboard()

@app.route('/qr/scanner')
def qr_scanner():
    # Initialize the QR code scanner
    cap = cv2.VideoCapture(0)
    while True:
        ret, frame = cap.read()
        if not ret:
            break
        
        # QR code detection logic goes here

        cv2.imshow('QR Scanner', frame)
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break

    cap.release()
    cv2.destroyAllWindows()
    return redirect(url_for('dashboard'))

if __name__ == '__main__':
    app.run(debug=True)