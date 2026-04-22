# Educational Chatbot & 3D Viewer

An educational web app with a **database-first chatbot** (keyword-based) and **Gemini AI fallback**, plus a **3D model viewer**. Built for XAMPP (PHP + MySQL) with a modern, easy-to-follow setup.

---

## What This Project Does

| Feature | Description |
|--------|-------------|
| **Chatbot** | Users ask questions. The app first looks up answers in a **MySQL knowledge base** (keyword matching). If no match is found, it uses **Google Gemini AI** as fallback. |
| **3D Viewer** | Users can **upload** a 3D file (`.glb`, `.gltf`, or `.obj`) and **view** it in the browser with orbit/zoom/pan controls. |

---

## Step-by-Step Setup (Expert Instructions)

### Prerequisites

- **XAMPP** installed (Apache + MySQL + PHP 7.4+).
- A **Google account** (for a free Gemini API key).
- A **modern browser** (Chrome, Firefox, Edge).

---

### Step 1: Place the Project

1. Copy the entire `newbot` folder into your XAMPP web root:
   - **Windows:** `C:\xampp\htdocs\newbot`
   - **Mac/Linux:** `/Applications/XAMPP/htdocs/newbot` or `~/htdocs/newbot`
2. Confirm this structure exists:
   ```
   newbot/
   ├── api/
   │   ├── chat.php
   │   ├── upload_3d.php
   │   └── list_3d.php
   ├── config.php
   ├── database.php
   ├── database/
   │   ├── schema.sql
   │   └── seed_example.sql
   ├── assets/
   │   ├── css/style.css
   │   └── js/chat.js, viewer3d.js
   ├── uploads/3d/
   ├── index.php
   └── README.md
   ```

---

### Step 2: Create the Database

1. Start **XAMPP**: open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. Open **phpMyAdmin**: in the browser go to `http://localhost/phpmyadmin`.
3. Create the database and tables:
   - Click the **Import** tab.
   - Choose file: `C:\xampp\htdocs\newbot\database\schema.sql` (or your path).
   - Click **Go**.
   - You should see database `chatbot_db` and tables `knowledge_base` and `chat_log`.
4. (Optional) Add more sample Q&A: Import `database/seed_example.sql` the same way.

**Alternative (command line):**

```bash
cd C:\xampp\mysql\bin
mysql -u root < C:\xampp\htdocs\newbot\database\schema.sql
```

---

### Step 3: Configure the Database Connection

1. Open `database.php` in the project root.
2. If your MySQL user, password, or database name differs from the default, edit:

   ```php
   $conn = new mysqli("localhost", "root", "", "chatbot_db");
   ```

3. Save the file.

---

### Step 4: Get a Gemini API Key (for AI Fallback)

1. Go to **Google AI Studio**: [https://aistudio.google.com/apikey](https://aistudio.google.com/apikey).
2. Sign in with your Google account.
3. Click **Create API key** (use an existing Google Cloud project or create one).
4. Copy the generated key (e.g. `AIza...`).

---

### Step 5: Add the Gemini API Key to the Project

1. Open `config.php` in the project root.
2. Set your key:

   ```php
   define("GEMINI_API_KEY", "AIzaSy...your_actual_key...");
   ```

3. Save the file.  
   **Security note:** Do not commit `config.php` with a real key to public repositories.

---

### Step 6: Run the Application

1. Ensure **Apache** and **MySQL** are running in XAMPP.
2. In your browser open: **`http://localhost/newbot/`** (or `http://localhost/newbot/index.php`).
3. You should see:
   - **Chatbot** tab: type a question and get an answer (from database or Gemini).
   - **3D Viewer** tab: use “Choose 3D file” to upload a `.glb`, `.gltf`, or `.obj` file and view it.

---

## How the Chatbot Works (Flow)

1. User sends a message.
2. **Database lookup:** The app searches the `knowledge_base` table by **keywords** and **question** text (LIKE match on important words).
3. **If a row matches:** That row’s `answer` is returned and shown with a “Database” label.
4. **If no row matches:** The app calls the **Gemini API** with the user’s message and returns the AI response with an “AI (Gemini)” label.
5. If the API key is missing or the request fails, the user sees a message asking them to add the key or try again.

---

## How the 3D Viewer Works

- **Frontend:** Uses **Three.js** (with GLTFLoader and OBJLoader) to load and render 3D models.
- **Interaction:** Orbit (rotate), zoom, and pan are handled by OrbitControls.
- **Upload:** The “Choose 3D file” input (and optional drag-and-drop) loads the file in the browser. Optionally, you can use `api/upload_3d.php` to upload to the server and then load the model from `uploads/3d/`.
- **Supported formats:** `.glb`, `.gltf`, `.obj` (max 20 MB if using server upload).

---

## Adding More Q&A to the Knowledge Base

1. In phpMyAdmin, open the `educational_chatbot` database and the `knowledge_base` table.
2. Click **Insert** and add:
   - **keywords:** Comma- or space-separated words that users might type (e.g. `gravity, force, earth`).
   - **question:** A short canonical question (e.g. `What is gravity?`).
   - **answer:** The full answer text.
   - **category:** Optional (e.g. `Physics`, `Biology`).

Or run SQL:

```sql
INSERT INTO knowledge_base (keywords, question, answer, category)
VALUES (
  'your, keywords, here',
  'Canonical question?',
  'Full answer text.',
  'Category'
);
```

---

## File Reference

| Path | Purpose |
|------|--------|
| `index.php` | Main page: Chatbot + 3D Viewer tabs. |
| `api/chat.php` | Chat API: DB lookup + Gemini fallback. |
| `api/upload_3d.php` | Upload 3D files to `uploads/3d/`. |
| `api/list_3d.php` | List uploaded 3D files (JSON). |
| `config.php` | Gemini API key and endpoint. |
| `database.php` | MySQL connection (`$conn`, database `chatbot_db`). |
| `database/schema.sql` | Creates DB and tables. |
| `database/seed_example.sql` | Extra sample Q&A. |
| `assets/css/style.css` | Page and component styles. |
| `assets/js/chat.js` | Chat UI and API calls. |
| `assets/js/viewer3d.js` | Three.js 3D viewer logic. |

---

## Troubleshooting

| Issue | What to check |
|-------|----------------|
| Blank or 500 error | PHP errors: check `C:\xampp\apache\logs\error.log`. Enable `display_errors` in `php.ini` if needed. |
| “Database” answers never show | Run `database/schema.sql` and confirm `knowledge_base` has rows. Check `database.php` (user/password/database name). |
| All answers from “AI (Gemini)” or “API unavailable” | Add a valid key in `config.php`. Ensure the server can reach `https://generativelanguage.googleapis.com`. |
| 3D model doesn’t load | Use a valid `.glb`/`.gltf`/`.obj` file. Open browser console (F12) for loader errors. |
| Upload fails (3D) | Ensure `uploads/3d/` exists and is writable (e.g. `chmod 755` on Linux/Mac). |

---

## Summary

- **Database** = main source of answers (keyword-based).
- **Gemini AI** = fallback when the database has no match.
- **3D Viewer** = upload/load 3D files and view them in the browser.

Follow the steps above in order (project placement → database → config → API key → run) for a clean, expert-level setup.
