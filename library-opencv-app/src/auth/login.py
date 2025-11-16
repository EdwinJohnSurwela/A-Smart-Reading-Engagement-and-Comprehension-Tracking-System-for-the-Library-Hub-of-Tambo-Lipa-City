from flask import Blueprint, request, jsonify
from src.database.models import User
from src.utils.responses import json_response

login_bp = Blueprint('login', __name__)

@login_bp.route('/login', methods=['POST'])
def login():
    data = request.get_json()
    email = data.get('email')
    password = data.get('password')

    if not email or not password:
        return json_response(False, 'Email and password are required.')

    user = User.query.filter_by(email=email).first()

    if user and user.verify_password(password):
        # Here you would typically create a session or token
        return json_response(True, 'Login successful.', {'user_id': user.id, 'full_name': user.full_name})
    
    return json_response(False, 'Invalid email or password.')