# Guide d'Accès Étudiant - TaaBia LMS

## 📚 Comment les étudiants accèdent aux ressources du cours

### 🔐 Connexion

1. Les étudiants se connectent via `/auth/login.php`
2. Après connexion, ils sont redirigés vers `/student/index.php` (Dashboard)

---

## 🎯 Navigation Principale (Sidebar)

### 1. **Tableau de Bord** (`/student/index.php`)

- Vue d'ensemble des cours inscrits
- Statistiques de progrès
- Devoirs à venir
- Certificats récents
- Cours recommandés

### 2. **Mes Cours** (`/student/my_courses.php`)

- Liste de tous les cours auxquels l'étudiant est inscrit
- Filtres : recherche, statut, tri
- Affichage du progrès par cours
- Accès rapide aux cours

### 3. **Découvrir les Cours** (`/student/all_courses.php`)

- Catalogue complet des cours disponibles
- Possibilité de s'inscrire à de nouveaux cours
- Informations détaillées sur chaque cours

### 4. **Mes Leçons** (`/student/course_lessons.php`)

- Vue consolidée de toutes les leçons
- Accès direct au contenu des leçons
- Suivi de progression par leçon

---

## 📖 Accès aux Matériels de Cours

### **Flux d'Accès au Contenu :**

```
Mes Cours → Sélectionner un Cours → Voir le Cours → Leçons
```

#### Étape 1 : Voir un Cours (`/student/view_course.php?course_id=X`)

- Informations complètes sur le cours
- Liste de toutes les leçons du cours
- Barre de progression
- Certificat (si cours complété à 100%)
- Bouton pour contacter l'instructeur

#### Étape 2 : Visualiser une Leçon (`/student/view_lesson.php?lesson_id=X`)

**Types de contenu supportés :**

- 📹 **Vidéos** : Lecteur vidéo intégré
- 📄 **Documents PDF** : Visualiseur PDF intégré
- 📝 **Texte** : Contenu texte formaté
- 🎥 **Contenu externe** : Liens YouTube, etc.

**Fonctionnalités :**

- Suivi automatique du progrès
- Navigation entre leçons (Précédent/Suivant)
- Téléchargement des ressources
- Retour au cours

---

## 📝 Devoirs (Assignments)

### **Page : `/student/assignments.php`** ✅ **CRÉÉE**

**Fonctionnalités :**

- 📋 Liste de tous les devoirs des cours inscrits
- 🎯 Filtrage par cours et statut
- 📊 Statistiques : Total, En attente, Soumis, Notés
- 📈 Moyenne des notes

**Statuts des Devoirs :**

1. **En Attente** (Pending) : À soumettre

   - Affichage de la date limite
   - Alerte si en retard
   - Bouton "Soumettre le devoir"

2. **Soumis** (Submitted) : En correction

   - Date de soumission affichée
   - En attente de notation

3. **Noté** (Graded) : Corrigé
   - Note affichée
   - Commentaires de l'instructeur
   - Possibilité de consulter les détails

**Actions Disponibles :**

- ✍️ Soumettre un devoir (`submit_assignment.php`)
- 👁️ Voir les détails (`view_assignment.php`)
- 📥 Télécharger les instructions

---

## 🎯 Quiz

### **Page : `/student/quizzes.php`** ✅ **CRÉÉE**

**Fonctionnalités :**

- 📝 Liste de tous les quiz des cours inscrits
- 🔍 Filtrage par cours et statut
- 📊 Statistiques : Total, Non commencé, Complétés
- 📈 Score moyen

**Statuts des Quiz :**

1. **Non Commencé** (Not Started)

   - Informations sur le quiz
   - Temps limite (si applicable)
   - Score de passage requis
   - Bouton "Commencer le quiz"

2. **Complété** (Completed)
   - Score obtenu affiché
   - Statut : Réussi/Échoué
   - Temps pris
   - Date de complétion
   - Option de refaire (si autorisé)

**Actions Disponibles :**

- ▶️ Commencer un quiz (`take_quiz.php`)
- 📊 Voir les résultats (`quiz_results.php`)
- 🔄 Refaire le quiz (si autorisé)

---

## 📅 Présence (Attendance)

### **Page : `/student/attendance.php`** ✅ **EXISTE**

**Fonctionnalités :**

- 📊 Suivi de présence globale
- 📚 Présence par cours (`course_attendance.php`)
- 📈 Statistiques de présence
- 📆 Historique des présences

---

## 📧 Messages

### **Page : `/student/messages.php`** ✅ **EXISTE**

**Fonctionnalités :**

- 💬 Communication avec les instructeurs
- 📬 Boîte de réception des messages
- ✉️ Envoi de nouveaux messages (`send_message.php`)
- 👁️ Lecture des messages (`view_message.php`)
- 🔴 Badge de messages non lus

---

## 🎓 Certificats

### **Page : `/student/my_certificates.php`** ✅ **EXISTE**

**Fonctionnalités :**

- 🏆 Liste des certificats obtenus
- 📄 Visualisation des certificats
- 📥 Téléchargement en PDF
- ✅ Certificats générés automatiquement à 100% de progression

---

## 🛒 Mes Achats

### **Page : `/student/orders.php`** ✅ **EXISTE**

**Fonctionnalités :**

- 📦 Historique des commandes
- 💳 Détails des paiements
- 🔍 Voir les détails de commande (`view_order.php`)
- ❌ Annuler une commande (`cancel_order.php`)

---

## 👤 Profil

### **Page : `/student/profile.php`** ✅ **EXISTE**

**Fonctionnalités :**

- 📝 Informations personnelles
- ✏️ Modifier le profil (`edit_profile.php`)
- 🌍 Paramètres de langue (`language_settings.php`)
- 🔒 Gestion du compte

---

## 🗄️ Schéma de Base de Données Requis

### **Tables Existantes :**

✅ `courses` - Informations sur les cours
✅ `lessons` - Contenu des leçons
✅ `student_courses` - Inscriptions des étudiants
✅ `lesson_progress` - Suivi de progression
✅ `course_certificates` - Certificats générés
✅ `attendance` - Présence des étudiants
✅ `messages` - Messagerie
✅ `orders` - Commandes et paiements

### **Tables à Créer (pour Assignments & Quiz) :**

#### Table `assignments`

```sql
CREATE TABLE assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    deadline DATETIME NOT NULL,
    max_grade INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);
```

#### Table `assignment_submissions`

```sql
CREATE TABLE assignment_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255),
    submission_text TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    grade DECIMAL(5,2),
    feedback TEXT,
    graded_at DATETIME,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### Table `quizzes`

```sql
CREATE TABLE quizzes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    time_limit INT, -- en minutes
    passing_score INT DEFAULT 70,
    allow_retake BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);
```

#### Table `quiz_attempts`

```sql
CREATE TABLE quiz_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    score DECIMAL(5,2),
    time_taken INT, -- en secondes
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## 📱 Responsive Design

Toutes les pages étudiantes sont **responsive** et s'adaptent aux :

- 💻 Ordinateurs de bureau
- 📱 Tablettes
- 📲 Smartphones

---

## 🎨 Interface Utilisateur

**Design moderne avec :**

- Gradient violet élégant
- Cartes avec ombres et animations
- Icônes Font Awesome
- Badges de statut colorés
- Barres de progression visuelles
- Navigation intuitive

---

## 🔄 Flux de Travail Étudiant Complet

### Parcours d'Apprentissage :

1. **Inscription à un cours**

   - Découvrir → Acheter/S'inscrire → Confirmation

2. **Apprentissage**

   - Mes Cours → Voir le Cours → Leçons → Visionner le Contenu

3. **Évaluations**

   - Devoirs → Soumettre → Attendre la correction → Voir la note
   - Quiz → Commencer → Répondre aux questions → Voir le score

4. **Suivi**

   - Dashboard → Statistiques de progrès
   - Présence → Vérifier l'assiduité

5. **Certification**
   - Compléter 100% du cours → Certificat généré automatiquement → Télécharger

---

## 🔗 Pages Supplémentaires à Créer (Optionnel)

1. **`submit_assignment.php`** - Formulaire de soumission de devoir
2. **`view_assignment.php`** - Détails complets d'un devoir
3. **`take_quiz.php`** - Interface pour passer un quiz
4. **`quiz_results.php`** - Résultats détaillés du quiz

---

## 🌍 Support Multi-langue

Le système utilise :

- Fonction `__()` pour toutes les traductions
- Fichiers de langue : `/lang/fr.php` et `/lang/en.php`
- Changement de langue via `/student/language_settings.php`

---

## 📞 Support & Contact

Les étudiants peuvent :

- Envoyer des messages aux instructeurs via la messagerie intégrée
- Contacter l'administration via le formulaire de contact
- Consulter les FAQ (si disponible)

---

## ✅ Résumé - Ce qui Fonctionne

| Fonctionnalité         | Status | Page                                 |
| ---------------------- | ------ | ------------------------------------ |
| **Matériels de Cours** | ✅     | `view_course.php`, `view_lesson.php` |
| **Devoirs**            | ✅     | `assignments.php`                    |
| **Quiz**               | ✅     | `quizzes.php`                        |
| **Présence**           | ✅     | `attendance.php`                     |
| **Certificats**        | ✅     | `my_certificates.php`                |
| **Messages**           | ✅     | `messages.php`                       |
| **Profil**             | ✅     | `profile.php`                        |
| **Achats**             | ✅     | `orders.php`                         |

---

## 🚀 Prochaines Étapes

1. **Créer les tables de base de données** pour assignments et quizzes
2. **Développer les pages de soumission** (`submit_assignment.php`, `take_quiz.php`)
3. **Tester le flux complet** de l'étudiant
4. **Ajouter des notifications** pour les nouveaux devoirs/quiz
5. **Implémenter un calendrier** pour les échéances

---

**Date de création :** Octobre 2025  
**Version :** 1.0  
**Auteur :** TaaBia LMS Team









