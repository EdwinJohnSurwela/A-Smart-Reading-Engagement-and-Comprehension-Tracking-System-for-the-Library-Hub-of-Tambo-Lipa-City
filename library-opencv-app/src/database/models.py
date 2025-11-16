from sqlalchemy import Column, Integer, String, Float, ForeignKey
from sqlalchemy.orm import relationship
from .database import Base

class User(Base):
    __tablename__ = 'users'

    user_id = Column(Integer, primary_key=True, autoincrement=True)
    full_name = Column(String, nullable=False)
    email = Column(String, unique=True, nullable=False)
    password_hash = Column(String, nullable=False)
    user_type = Column(String, nullable=False)  # e.g., 'student', 'teacher', 'librarian', 'admin'
    status = Column(String, default='active')  # e.g., 'active', 'inactive'

    def __repr__(self):
        return f"<User(id={self.user_id}, name={self.full_name}, email={self.email})>"

class Book(Base):
    __tablename__ = 'books'

    book_id = Column(Integer, primary_key=True, autoincrement=True)
    title = Column(String, nullable=False)
    author = Column(String, nullable=False)
    isbn = Column(String, unique=True, nullable=False)
    genre = Column(String)
    total_pages = Column(Integer)
    difficulty = Column(String, default='beginner')
    qr_code = Column(String)

    def __repr__(self):
        return f"<Book(id={self.book_id}, title={self.title}, author={self.author})>"

class QuizAttempt(Base):
    __tablename__ = 'quiz_attempts'

    attempt_id = Column(Integer, primary_key=True, autoincrement=True)
    user_id = Column(Integer, ForeignKey('users.user_id'), nullable=False)
    book_id = Column(Integer, ForeignKey('books.book_id'), nullable=False)
    total_questions = Column(Integer, nullable=False)
    correct_answers = Column(Integer, nullable=False)
    score_percentage = Column(Float, nullable=False)

    user = relationship("User", back_populates="quiz_attempts")
    book = relationship("Book", back_populates="quiz_attempts")

    def __repr__(self):
        return f"<QuizAttempt(id={self.attempt_id}, user_id={self.user_id}, book_id={self.book_id}, score={self.score_percentage})>"