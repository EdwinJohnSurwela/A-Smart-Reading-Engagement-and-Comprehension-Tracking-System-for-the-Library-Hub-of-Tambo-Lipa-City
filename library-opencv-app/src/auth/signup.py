from flask import Blueprint, request, jsonify
from src.database.models import User
from src.utils.responses import json_response
from werkzeug.security import generate_password_hash

signup_bp = Blueprint('signup', __name__)

@signup_bp.route('/signup', methods=['POST'])
def signup():
    data = request.get_json()
    
    if not data or 'email' not in data or 'password' not in data:
        return json_response(False, 'Email and password are required.')

    email = data['email']
    password = data['password']
    
    # Check if user already exists
    existing_user = User.query.filter_by(email=email).first()
    if existing_user:
        return json_response(False, 'User already exists.')

    # Create new user
    new_user = User(email=email, password=generate_password_hash(password))
    
    try:
        new_user.save()  # Assuming save method exists in User model
        return json_response(True, 'User registered successfully.')
    except Exception as e:
        return json_response(False, str(e))