def success_response(data, message="Success"):
    return {
        "status": "success",
        "message": message,
        "data": data
    }

def error_response(message="An error occurred", code=400):
    return {
        "status": "error",
        "message": message,
        "code": code
    }

def not_found_response(message="Resource not found"):
    return error_response(message, code=404)

def unauthorized_response(message="Unauthorized access"):
    return error_response(message, code=401)