from flask import Blueprint, render_template
from src.database.models import User, Book

dashboard_bp = Blueprint('dashboard', __name__)

@dashboard_bp.route('/dashboard')
def dashboard():
    # Fetch statistics for the dashboard
    total_users = User.query.count()
    total_books = Book.query.count()
    # Add more statistics as needed

    return render_template('dashboard.html', total_users=total_users, total_books=total_books)