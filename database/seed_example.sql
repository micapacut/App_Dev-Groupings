-- Optional: More example Q&A entries (run after schema.sql)
USE chatbot_db;

INSERT INTO knowledge_base (keywords, question, answer, category) VALUES
('atom, element, nucleus', 'What is an atom?', 'An atom is the smallest unit of ordinary matter that forms a chemical element. It consists of a nucleus (protons and neutrons) and electrons orbiting the nucleus.', 'Chemistry'),
('python, programming, code', 'What is Python?', 'Python is a high-level, interpreted programming language known for its readability and versatility. It is widely used in web development, data science, and automation.', 'Computer Science');
