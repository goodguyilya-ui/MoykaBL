"""
Flask backend for korochki_carwash (migrated from PHP API).
Run from project root: python app.py
Requires MySQL database (see db/korochki_carwash.sql).
"""
from __future__ import annotations

import hmac
import os
import re
from datetime import date, datetime, time, timedelta
from functools import wraps
from pathlib import Path
from typing import Any

import bcrypt
import pymysql
from dotenv import load_dotenv
from flask import Flask, jsonify, request, send_from_directory, abort
from pymysql.cursors import DictCursor

load_dotenv()

ROOT = Path(__file__).resolve().parent

app = Flask(__name__, static_folder=None)


def get_conn() -> pymysql.connections.Connection:
    return pymysql.connect(
        host=os.getenv("MYSQL_HOST", "127.0.0.1"),
        port=int(os.getenv("MYSQL_PORT", "3306")),
        user=os.getenv("MYSQL_USER", "root"),
        password=os.getenv("MYSQL_PASSWORD", ""),
        database=os.getenv("MYSQL_DB", "korochki_carwash"),
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=False,
    )


def verify_password(password: str, stored: str) -> bool:
    """PHP password_hash (bcrypt) or legacy plain text (as in seed data)."""
    if not stored:
        return False
    if stored.startswith("$2"):
        pw = password.encode("utf-8")
        try:
            if bcrypt.checkpw(pw, stored.encode("utf-8")):
                return True
        except ValueError:
            pass
        if stored.startswith("$2y$"):
            try:
                fixed = ("$2b$" + stored[4:]).encode("utf-8")
                return bcrypt.checkpw(pw, fixed)
            except ValueError:
                return False
        return False
    if len(password) != len(stored):
        return False
    return hmac.compare_digest(password, stored)


def hash_password(password: str) -> str:
    return bcrypt.hashpw(password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")


def format_cell(v: Any) -> Any:
    if isinstance(v, (datetime, date)):
        return v.isoformat() if isinstance(v, datetime) else str(v)
    if isinstance(v, time):
        return v.strftime("%H:%M:%S")
    if isinstance(v, timedelta):
        total = int(v.total_seconds()) % (24 * 3600)
        h, rem = divmod(total, 3600)
        m, s = divmod(rem, 60)
        return f"{h:02d}:{m:02d}:{s:02d}"
    return v


def serialize_row(row: dict[str, Any]) -> dict[str, Any]:
    return {k: format_cell(v) for k, v in row.items()}


def api_error(message: str, status: int = 400):
    return jsonify(error=message), status


def require_json() -> dict[str, Any]:
    data = request.get_json(silent=True, force=True)
    return data if isinstance(data, dict) else {}


def field(data: dict[str, Any], key: str) -> str:
    return str(data.get(key) or "").strip()


def handle_db_errors(f):
    @wraps(f)
    def wrapped(*args, **kwargs):
        try:
            return f(*args, **kwargs)
        except pymysql.err.OperationalError as e:
            msg = str(e.args[1]) if len(e.args) > 1 else str(e)
            if "access denied" in msg.lower():
                msg = "Ошибка подключения к MySQL: проверьте логин/пароль в .env."
            return api_error(msg, 500)
        except pymysql.MySQLError as e:
            return api_error(str(e), 500)

    return wrapped


# --- API ---


@app.post("/api/login")
@handle_db_errors
def api_login():
    data = require_json()
    login_val = field(data, "login")
    password = str(data.get("password") or "")
    if not login_val or not password:
        return api_error("Введите логин и пароль.", 400)

    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT u.id, u.login, u.password_hash, r.name AS role
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.login = %s
                LIMIT 1
                """,
                (login_val,),
            )
            user = cur.fetchone()

    if not user or not verify_password(password, str(user["password_hash"])):
        return api_error("Неверный логин или пароль.", 401)

    return jsonify(ok=True, user_id=int(user["id"]), role=str(user["role"]))


@app.post("/api/register")
@handle_db_errors
def api_register():
    data = require_json()
    login_val = field(data, "login")
    password = str(data.get("password") or "")
    full_name = field(data, "full_name")
    phone = field(data, "phone")
    email = field(data, "email").lower()

    if not re.fullmatch(r"^[A-Za-z0-9]{6,}$", login_val):
        return api_error("Логин: латиница/цифры, минимум 6 символов.", 400)
    if len(password) < 8:
        return api_error("Пароль должен быть не менее 8 символов.", 400)
    if not re.fullmatch(r"^[А-Яа-яЁё\s]+$", full_name):
        return api_error("ФИО: только кириллица и пробелы.", 400)
    if not re.fullmatch(r"^8\(\d{3}\)\d{3}-\d{2}-\d{2}$", phone):
        return api_error("Телефон в формате 8(XXX)XXX-XX-XX.", 400)
    if not re.fullmatch(r"^[^@]+@[^@]+\.[^@]+$", email):
        return api_error("Некорректный email.", 400)

    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM users WHERE login = %s OR email = %s LIMIT 1",
                (login_val, email),
            )
            if cur.fetchone():
                return api_error("Логин или email уже используются.", 409)

            cur.execute("SELECT id FROM roles WHERE name = 'client' LIMIT 1")
            row = cur.fetchone()
            role_id = int(row["id"]) if row else 0
            if role_id <= 0:
                return api_error("В БД отсутствует роль client.", 500)

            cur.execute(
                """
                INSERT INTO users (login, password_hash, full_name, phone, email, role_id)
                VALUES (%s, %s, %s, %s, %s, %s)
                """,
                (login_val, hash_password(password), full_name, phone, email, role_id),
            )
        conn.commit()

    return jsonify(ok=True, message="Пользователь создан.")


@app.post("/api/create_application")
@handle_db_errors
def api_create_application():
    data = require_json()
    user_id = int(data.get("user_id") or 0)
    course_name = field(data, "course_name")
    car_model = field(data, "car_model")
    visit_date = field(data, "visit_date")
    visit_time = field(data, "visit_time")
    payment_method = field(data, "payment_method")

    if (
        user_id <= 0
        or not course_name
        or not car_model
        or not visit_date
        or not visit_time
        or not payment_method
    ):
        return api_error("Заполните все поля заявки.", 400)

    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM courses WHERE name = %s LIMIT 1", (course_name,)
            )
            cr = cur.fetchone()
            course_id = int(cr["id"]) if cr else 0

            cur.execute(
                "SELECT id FROM payment_methods WHERE name = %s LIMIT 1",
                (payment_method,),
            )
            pr = cur.fetchone()
            payment_id = int(pr["id"]) if pr else 0

            cur.execute(
                "SELECT id FROM application_statuses WHERE name = 'Новая' LIMIT 1"
            )
            sr = cur.fetchone()
            status_id = int(sr["id"]) if sr else 0

            if course_id <= 0 or payment_id <= 0 or status_id <= 0:
                return api_error(
                    "Ошибка справочников БД (курсы/оплата/статусы).", 500
                )

            cur.execute(
                """
                INSERT INTO applications (
                  user_id, course_id, car_model, visit_date, visit_time,
                  payment_method_id, status_id
                ) VALUES (%s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    user_id,
                    course_id,
                    car_model,
                    visit_date,
                    visit_time,
                    payment_id,
                    status_id,
                ),
            )
        conn.commit()

    return jsonify(ok=True, message="Заявка создана.")


@app.get("/api/get_user_applications")
@handle_db_errors
def api_get_user_applications():
    user_id = int(request.args.get("user_id") or 0)
    if user_id <= 0:
        return api_error("Не указан user_id.", 400)

    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT
                  a.id,
                  c.name AS course_name,
                  a.car_model,
                  a.visit_date,
                  a.visit_time,
                  pm.name AS payment_method,
                  st.name AS status
                FROM applications a
                INNER JOIN courses c ON c.id = a.course_id
                INNER JOIN payment_methods pm ON pm.id = a.payment_method_id
                INNER JOIN application_statuses st ON st.id = a.status_id
                WHERE a.user_id = %s
                ORDER BY a.id DESC
                """,
                (user_id,),
            )
            rows = cur.fetchall()

    items = [serialize_row(dict(r)) for r in rows]
    return jsonify(ok=True, items=items)


@app.get("/api/get_admin_applications")
@handle_db_errors
def api_get_admin_applications():
    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT
                  a.id,
                  a.user_id,
                  c.name AS course_name,
                  a.car_model,
                  a.visit_date,
                  a.visit_time,
                  st.name AS status
                FROM applications a
                INNER JOIN courses c ON c.id = a.course_id
                INNER JOIN application_statuses st ON st.id = a.status_id
                ORDER BY a.id DESC
                """
            )
            rows = cur.fetchall()

    items = [serialize_row(dict(r)) for r in rows]
    return jsonify(ok=True, items=items)


@app.post("/api/update_application_status")
@handle_db_errors
def api_update_application_status():
    data = require_json()
    application_id = int(data.get("application_id") or 0)
    status_name = field(data, "status")

    if application_id <= 0 or not status_name:
        return api_error("Некорректные данные обновления статуса.", 400)

    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM application_statuses WHERE name = %s LIMIT 1",
                (status_name,),
            )
            sr = cur.fetchone()
            status_id = int(sr["id"]) if sr else 0
            if status_id <= 0:
                return api_error("Неизвестный статус.", 400)

            cur.execute(
                "UPDATE applications SET status_id = %s WHERE id = %s",
                (status_id, application_id),
            )
        conn.commit()

    return jsonify(ok=True)


@app.post("/api/create_review")
@handle_db_errors
def api_create_review():
    data = require_json()
    user_id = int(data.get("user_id") or 0)
    text = field(data, "text")

    if user_id <= 0 or not text:
        return api_error("Некорректные данные отзыва.", 400)

    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT COUNT(*) AS completed_count
                FROM applications a
                INNER JOIN application_statuses st ON st.id = a.status_id
                WHERE a.user_id = %s
                  AND st.name = 'Обучение завершено'
                """,
                (user_id,),
            )
            cnt = int((cur.fetchone() or {}).get("completed_count") or 0)
            if cnt <= 0:
                return api_error(
                    'Отзыв доступен только после статуса "Обучение завершено".', 403
                )

            cur.execute(
                "INSERT INTO reviews (user_id, text) VALUES (%s, %s)",
                (user_id, text),
            )
        conn.commit()

    return jsonify(ok=True, message="Отзыв отправлен.")


# --- Static site (HTML/CSS/JS from project root) ---


@app.route("/", defaults={"path": "index.html"})
@app.route("/<path:path>")
def serve_frontend(path: str):
    if path.startswith("api/"):
        abort(404)
    safe = (ROOT / path).resolve()
    try:
        safe.relative_to(ROOT)
    except ValueError:
        abort(404)
    if not safe.is_file():
        abort(404)
    return send_from_directory(ROOT, path)


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=int(os.getenv("FLASK_PORT", "5000")), debug=True)
