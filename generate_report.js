const docx = require('docx');
const fs = require('fs');
const path = require('path');

const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
  HeadingLevel, AlignmentType, WidthType, ShadingType,
  Header, Footer, PageNumber, convertInchesToTwip
} = docx;

const FONT = 'Times New Roman';
const BODY_SIZE = 24;
const TITLE_SIZE = 28;

function br() { return new Paragraph({ spacing: { after: 120 } }); }

function h1(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_1,
    spacing: { before: 360, after: 200 },
    children: [new TextRun({ text, bold: true, font: FONT, size: TITLE_SIZE })],
  });
}

function h2(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_2,
    spacing: { before: 240, after: 140 },
    children: [new TextRun({ text, bold: true, font: FONT, size: BODY_SIZE })],
  });
}

function h3(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_3,
    spacing: { before: 200, after: 120 },
    children: [new TextRun({ text, bold: true, font: FONT, size: BODY_SIZE })],
  });
}

function para(text, opts = {}) {
  const children = [];
  if (typeof text === 'string') {
    children.push(new TextRun({ text, font: FONT, size: BODY_SIZE, ...opts }));
  } else if (Array.isArray(text)) {
    text.forEach(t => {
      if (typeof t === 'string') children.push(new TextRun({ text: t, font: FONT, size: BODY_SIZE, ...opts }));
      else children.push(new TextRun({ font: FONT, size: BODY_SIZE, ...t }));
    });
  }
  return new Paragraph({
    spacing: { after: 120, line: 312 },
    alignment: AlignmentType.JUSTIFIED,
    children,
  });
}

function bp(label, value) {
  return new Paragraph({
    spacing: { after: 80, line: 300 },
    alignment: AlignmentType.JUSTIFIED,
    children: [
      new TextRun({ text: label, font: FONT, size: BODY_SIZE, bold: true }),
      new TextRun({ text: value, font: FONT, size: BODY_SIZE }),
    ],
  });
}

function bul(text) {
  return new Paragraph({
    spacing: { after: 60, line: 300 },
    indent: { left: convertInchesToTwip(0.5) },
    children: [
      new TextRun({ text: '\u2022 ', font: FONT, size: BODY_SIZE }),
      new TextRun({ text, font: FONT, size: BODY_SIZE }),
    ],
  });
}

function nbul(num, text) {
  return new Paragraph({
    spacing: { after: 60, line: 300 },
    indent: { left: convertInchesToTwip(0.5) },
    children: [
      new TextRun({ text: `${num}. `, font: FONT, size: BODY_SIZE }),
      new TextRun({ text, font: FONT, size: BODY_SIZE }),
    ],
  });
}

function tcell(text, bold = false, width = 2000) {
  return new TableCell({
    width: { size: width, type: WidthType.DXA },
    children: [new Paragraph({
      alignment: AlignmentType.LEFT,
      children: [new TextRun({ text, font: FONT, size: 20, bold })],
    })],
  });
}

function thcell(text, width = 2000) {
  return new TableCell({
    width: { size: width, type: WidthType.DXA },
    shading: { type: ShadingType.CLEAR, fill: '1E3A5F' },
    children: [new Paragraph({
      alignment: AlignmentType.LEFT,
      children: [new TextRun({ text, font: FONT, size: 20, bold: true, color: 'FFFFFF' })],
    })],
  });
}

function mkTable(headers, rows, colWidths) {
  const headerRow = new TableRow({
    tableHeader: true,
    children: headers.map((h, i) => thcell(h, colWidths ? colWidths[i] : 2000)),
  });
  const dataRows = rows.map(row => new TableRow({
    children: row.map((c, i) => tcell(c, false, colWidths ? colWidths[i] : 2000)),
  }));
  return new Table({
    rows: [headerRow, ...dataRows],
    width: { size: 100, type: WidthType.PERCENTAGE },
  });
}

const empty = [];

// =============================================
// BUILD SECTIONS
// =============================================
// TITLE PAGE
const titleSection = {
  children: [
    br(), br(), br(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 100 },
      children: [new TextRun({ text: 'A PROJECT REPORT ON', font: FONT, size: TITLE_SIZE, bold: true })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 100 },
      children: [new TextRun({ text: '"AI-Based Adaptive Online Examination and', font: FONT, size: TITLE_SIZE, bold: true })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 100 },
      children: [new TextRun({ text: 'Student Performance Analysis System"', font: FONT, size: TITLE_SIZE, bold: true })],
    }),
    br(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 100 },
      children: [new TextRun({ text: 'Submitted in Partial Fulfillment of the Requirements for', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 100 },
      children: [new TextRun({ text: 'Summer Research Internship (6 Weeks)', font: FONT, size: TITLE_SIZE, bold: true, color: '1E3A5F' })],
    }),
    br(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 100 },
      children: [new TextRun({ text: 'SUBMITTED BY', font: FONT, size: BODY_SIZE, bold: true })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 60 },
      children: [new TextRun({ text: 'KISHAN PAREKH', font: FONT, size: BODY_SIZE, bold: true })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 100 },
      children: [new TextRun({ text: 'Enrollment No: [Your Enrollment Number]', font: FONT, size: BODY_SIZE })],
    }),
    br(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 80 },
      children: [new TextRun({ text: 'Under the Guidance of', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 100 },
      children: [new TextRun({ text: '[Guide Name]', font: FONT, size: BODY_SIZE, bold: true })],
    }),
    br(), br(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 80 },
      children: [new TextRun({ text: 'Master of Computer Applications (MCA)', font: FONT, size: BODY_SIZE, bold: true })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 60 },
      children: [new TextRun({ text: '[Department Name]', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 60 },
      children: [new TextRun({ text: '[College Name]', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 60 },
      children: [new TextRun({ text: '[University Name]', font: FONT, size: BODY_SIZE })],
    }),
    br(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      children: [new TextRun({ text: '[Month, Year]', font: FONT, size: BODY_SIZE })],
    }),
  ],
};

// CERTIFICATE
const certSection = {
  children: [
    br(), br(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 240 },
      children: [new TextRun({ text: 'CERTIFICATE', font: FONT, size: 36, bold: true })],
    }),
    br(),
    new Paragraph({
      spacing: { after: 120, line: 360 },
      alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun({
        text: 'This is to certify that the internship report entitled "AI-Based Adaptive Online Examination and Student Performance Analysis System" submitted by KISHAN PAREKH (Enrollment No: [Your Enrollment Number]) to the [Department Name], [College Name], [University Name] in partial fulfillment of the requirements for the Summer Research Internship (6 Weeks) of Master of Computer Applications (MCA) is a record of bonafide project work carried out by him under my supervision and guidance.',
        font: FONT, size: BODY_SIZE,
      })],
    }),
    br(),
    new Paragraph({
      spacing: { after: 120, line: 360 },
      alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun({
        text: 'The results embodied in this internship report have not been submitted to any other University or Institute for the award of any degree or diploma.',
        font: FONT, size: BODY_SIZE,
      })],
    }),
    br(), br(), br(), br(),
    new Paragraph({ spacing: { after: 240 }, children: [new TextRun({ text: 'Internship Guide', font: FONT, size: BODY_SIZE, bold: true })] }),
    new Paragraph({ spacing: { after: 240 }, children: [new TextRun({ text: 'Head of Department', font: FONT, size: BODY_SIZE, bold: true })] }),
    new Paragraph({ spacing: { after: 240 }, children: [new TextRun({ text: 'External Examiner', font: FONT, size: BODY_SIZE, bold: true })] }),
  ],
};

// ACKNOWLEDGEMENT
const ackSection = {
  children: [
    br(),
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 240 },
      children: [new TextRun({ text: 'ACKNOWLEDGEMENT', font: FONT, size: 36, bold: true })],
    }),
    br(),
    new Paragraph({
      spacing: { after: 120, line: 360 }, alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun({ text: 'I would like to express my sincere gratitude to my internship guide [Guide Name] for their invaluable guidance, continuous support, and encouragement throughout the duration of my Summer Research Internship. Their expertise and insights were instrumental in shaping this work.', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      spacing: { after: 120, line: 360 }, alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun({ text: 'I extend my heartfelt thanks to the Head of Department and faculty members of the MCA program for providing the necessary resources and infrastructure to carry out this internship successfully.', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      spacing: { after: 120, line: 360 }, alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun({ text: 'I am grateful to my family and friends for their unwavering support and motivation throughout this internship journey.', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      spacing: { after: 120, line: 360 }, alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun({ text: 'Finally, I would like to acknowledge the open-source community for providing excellent tools and libraries including PHP, MySQL, Bootstrap, Chart.js, and the Groq API that made this project possible during the internship.', font: FONT, size: BODY_SIZE })],
    }),
    br(), br(),
    new Paragraph({ alignment: AlignmentType.RIGHT, children: [new TextRun({ text: 'KISHAN PAREKH', font: FONT, size: BODY_SIZE, bold: true })] }),
  ],
};

// ABSTRACT
const abstractSection = {
  children: [
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 240 },
      children: [new TextRun({ text: 'ABSTRACT', font: FONT, size: 36, bold: true })],
    }),
    br(),
    new Paragraph({
      spacing: { after: 120, line: 360 }, alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun({ text: 'The "AI-Based Adaptive Online Examination and Student Performance Analysis System" is a web-based platform designed to modernize the traditional examination process by integrating artificial intelligence and adaptive testing methodologies. Developed using Core PHP, MySQLi, Bootstrap 5, JavaScript, and the Groq AI API (Llama 3.3-70B), the system addresses critical challenges in manual examination management including paper-based administration, delayed result processing, lack of personalized assessment, and limited performance analytics.', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      spacing: { after: 120, line: 360 }, alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun({ text: 'The system features a robust administration module enabling comprehensive management of students, subjects, question banks, examinations, and results. A distinctive capability is the adaptive examination engine that implements a simplified Item Response Theory (IRT) algorithm to dynamically adjust question difficulty based on real-time student performance, providing a personalized assessment experience. The AI integration via Groq API enables intelligent question generation in multiple languages (English, Hindi, Gujarati), automated student performance analysis with personalized recommendations, skill categorization using theta scores, and actionable dashboard insights for administrators.', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      spacing: { after: 120, line: 360 }, alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun({ text: 'Key features include role-based authentication, timed examinations with automatic submission, real-time adaptive testing with theta score tracking, detailed result analytics with Chart.js visualizations, PDF report generation via FPDF, notification management, feedback systems, webcam-based attendance verification, and comprehensive performance dashboards for both students and administrators. The system automatically calculates results, generates pass/fail status, provides AI-powered insights, and supports multi-language question generation.', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      spacing: { after: 120, line: 360 }, alignment: AlignmentType.JUSTIFIED,
      children: [new TextRun({ text: 'The project is expected to reduce examination administrative overhead by automating result calculation and report generation, improve assessment quality through adaptive testing, provide actionable insights through AI-powered analytics, and deliver a scalable, cost-effective solution deployable on standard XAMPP environments without complex infrastructure requirements.', font: FONT, size: BODY_SIZE })],
    }),
    new Paragraph({
      spacing: { after: 80, line: 300 },
      children: [
        new TextRun({ text: 'Keywords: ', font: FONT, size: BODY_SIZE, bold: true }),
        new TextRun({ text: 'Adaptive Examination, AI-Powered Assessment, Computerized Adaptive Testing, Item Response Theory, Groq API, Performance Analysis, Online Examination System, Core PHP, MySQL.', font: FONT, size: BODY_SIZE }),
      ],
    }),
  ],
};

// TOC - Table of Contents in proper table format
function tocRow(chNum, title, pageNum) {
  return new TableRow({
    children: [
      new TableCell({
        width: { size: 1200, type: WidthType.DXA },
        children: [new Paragraph({
          alignment: AlignmentType.CENTER,
          children: [new TextRun({ text: chNum, font: FONT, size: BODY_SIZE, bold: true })],
        })],
      }),
      new TableCell({
        width: { size: 8000, type: WidthType.DXA },
        children: [new Paragraph({
          alignment: AlignmentType.LEFT,
          children: [new TextRun({ text: title, font: FONT, size: BODY_SIZE })],
        })],
      }),
      new TableCell({
        width: { size: 1200, type: WidthType.DXA },
        children: [new Paragraph({
          alignment: AlignmentType.CENTER,
          children: [new TextRun({ text: pageNum, font: FONT, size: BODY_SIZE })],
        })],
      }),
    ],
  });
}

const tocSection = {
  children: [
    new Paragraph({
      alignment: AlignmentType.CENTER,
      spacing: { after: 240 },
      children: [new TextRun({ text: 'TABLE OF CONTENTS', font: FONT, size: 36, bold: true })],
    }),
    br(),
    new Table({
      rows: [
        new TableRow({
          tableHeader: true,
          children: [
            thcell('Sr. No.', 1200),
            thcell('Chapter Title', 8000),
            thcell('Page No.', 1200),
          ],
        }),
        tocRow('1', 'Introduction', '1'),
        tocRow('2', 'Problem Statement and Objectives', '12'),
        tocRow('3', 'Requirement Analysis', '18'),
        tocRow('4', 'System Design', '25'),
        tocRow('5', 'Experimental Results', '38'),
        tocRow('6', 'Testing and Validation', '45'),
        tocRow('7', 'Conclusion', '50'),
        tocRow('8', 'References', '55'),
      ],
      width: { size: 100, type: WidthType.PERCENTAGE },
    }),
    br(),
    new Paragraph({
      spacing: { after: 80 },
      children: [new TextRun({
        text: '[Note: Page numbers are indicative. Update after inserting screenshots and final formatting in Word.]',
        font: FONT, size: 20, italics: true,
      })],
    }),
  ],
};

// CHAPTER 1
const ch1 = {
  children: [
    h1('1. Introduction'),
    h2('1.1 Background'),
    para('In the contemporary educational landscape, the transition from traditional pen-and-paper examinations to digital assessment platforms has become an imperative. Educational institutions worldwide are increasingly adopting online examination systems to streamline the evaluation process, reduce administrative overhead, and provide timely feedback to learners. However, most existing online examination platforms lack the intelligence to adapt to individual student capabilities, resulting in standardized assessments that fail to accurately measure true competency levels.'),
    para('The evolution of artificial intelligence and machine learning technologies presents unprecedented opportunities to revolutionize educational assessment. Computerized Adaptive Testing (CAT), grounded in Item Response Theory (IRT), offers a paradigm shift from fixed-form examinations to dynamic, personalized assessments that adjust question difficulty in real-time based on examinee responses. This approach not only reduces test length while maintaining measurement precision but also provides a more engaging and less frustrating experience for students of varying ability levels.'),
    h2('1.2 Importance of the System'),
    para('An AI-based adaptive online examination system addresses several critical needs in modern education. First, it eliminates the logistical challenges associated with paper-based examinations, including printing, distribution, and physical storage. Second, it provides immediate result processing, eliminating the delay between examination completion and result publication. Third, the adaptive testing mechanism ensures that each student receives a personalized assessment that accurately measures their knowledge level, providing more meaningful performance data than traditional one-size-fits-all examinations.'),
    para('Furthermore, the integration of AI-powered analytics transforms raw examination data into actionable insights. Students receive personalized feedback identifying their strengths and weaknesses, while administrators gain visibility into overall institutional performance trends, enabling data-driven decision-making for curriculum improvement and targeted interventions.'),
    h2('1.3 Current Problems in Traditional Examination Systems'),
    para('Traditional examination systems suffer from numerous challenges that compromise educational quality and administrative efficiency. The manual creation of question papers is time-consuming and prone to human error. The physical conduct of examinations requires significant resources including invigilation staff, examination halls, and printed materials. Result processing is delayed, often taking weeks or months, reducing the pedagogical value of timely feedback.'),
    para('Standardized examinations present questions of fixed difficulty to all students, regardless of their ability level. This approach fails to accurately differentiate between students at the extremes of the ability spectrum. High-ability students may find the examination too easy, while low-ability students may become frustrated by questions beyond their current knowledge level. Additionally, the absence of performance analytics limits the ability of educators to identify at-risk students and implement timely interventions.'),
    h2('1.4 Need of the System'),
    para('There is a pressing need for an intelligent online examination platform that combines the convenience of digital assessment with the sophistication of adaptive testing and AI-powered analytics. Educational institutions require a system that reduces administrative burden while improving assessment quality, provides immediate and actionable feedback to students, and offers comprehensive performance analytics to administrators.'),
    h2('1.5 Motivation'),
    para('The motivation for developing this project stems from the observation that most small to medium-sized educational institutions lack access to sophisticated examination management platforms. Commercial solutions are often prohibitively expensive and require complex infrastructure. Open-source alternatives frequently lack advanced features such as adaptive testing and AI integration. This project aims to bridge this gap by providing a comprehensive, feature-rich examination system that leverages freely available technologies and AI services to deliver professional-grade assessment capabilities.'),
    h2('1.6 Project Overview'),
    para('The AI-Based Adaptive Online Examination and Student Performance Analysis System is a web application developed using Core PHP and MySQLi on the server side, with Bootstrap 5, JavaScript, Chart.js, and custom CSS on the client side. The system architecture follows a three-tier design with presentation, business logic, and data access layers. The application is structured around two primary user roles: Administrator and Student.'),
    para('The system comprises fourteen database tables supporting comprehensive functionality including student management, subject management, question bank management, examination creation, standard and adaptive examination delivery, result processing, feedback collection, notification management, and attendance tracking. AI capabilities are integrated through the Groq API (Llama 3.3-70B), powering adaptive question generation, performance analysis, skill categorization, and dashboard insights.'),
    h2('1.7 Technologies Used'),
    para('The system is built using the following technologies:'),
    bul('PHP 8.0+ (Core PHP, no framework) - Server-side scripting and business logic'),
    bul('MySQLi - Database connectivity and query execution'),
    bul('HTML5 - Document structure and semantic markup'),
    bul('CSS3 - Styling, animations, and responsive design'),
    bul('Bootstrap 5.3 - Responsive layout, components, and utilities'),
    bul('JavaScript (Vanilla) - Client-side interactivity, form validation, and AJAX'),
    bul('Chart.js 4.4 - Interactive data visualization and analytics charts'),
    bul('SweetAlert2 - Enhanced user notifications and confirmation dialogs'),
    bul('FPDF - PDF document generation for report cards and results export'),
    bul('Groq API (Llama 3.3-70B) - AI question generation, performance analysis, and insights'),
    bul('Apache HTTP Server - Web server deployment via XAMPP'),
    h2('1.8 Scope'),
    para('The project scope encompasses the complete lifecycle of online examination management from student registration and examination administration to result processing and performance analytics. The system supports two examination modes: Standard mode with manually assigned questions, and Adaptive mode with AI-powered dynamic question selection. The adaptive engine uses a theta-based ability estimation algorithm to personalize the examination experience. AI integration extends to question generation in multiple languages, performance analysis with actionable recommendations, skill categorization, and administrative dashboard insights.'),
    para('The system includes comprehensive reporting capabilities including individual student report cards, exam-specific results, difficulty-wise performance analysis, and aggregated analytics with visual charts. PDF export functionality enables offline access to reports. The system also includes a notification system, feedback management, contact messaging, and webcam-based attendance verification.'),
  ],
};

// CHAPTER 2
const ch2 = {
  children: [
    h1('2. Problem Statement and Objectives'),
    h2('2.1 Problem Statement'),
    para('Educational institutions face significant challenges in conducting and managing examinations effectively. Traditional paper-based examination systems are plagued by logistical complexities, delayed result processing, and substantial resource consumption. The manual creation of question papers is inefficient and prone to errors and inconsistencies. Standardized examinations fail to accommodate varying student ability levels, resulting in assessments that do not accurately measure individual competency.'),
    para('Furthermore, the absence of intelligent analytics prevents educators from gaining actionable insights into student performance patterns. At-risk students often go unidentified until it is too late for intervention. The lack of personalized feedback limits the pedagogical value of examinations as learning tools. Small to medium-sized institutions, in particular, lack access to affordable, feature-rich examination management platforms that leverage modern technologies such as artificial intelligence and adaptive testing.'),
    h2('2.2 Objectives'),
    para('The primary objectives of this project are:'),
    nbul(1, 'To design and develop a web-based online examination system using Core PHP and MySQLi that enables administrators to manage students, subjects, question banks, and examinations efficiently.'),
    nbul(2, 'To implement an adaptive examination engine based on Item Response Theory principles that dynamically adjusts question difficulty based on real-time student performance, providing a personalized assessment experience.'),
    nbul(3, 'To integrate the Groq AI API for intelligent question generation in multiple languages (English, Hindi, Gujarati), automated student performance analysis, skill categorization, and administrative dashboard insights.'),
    nbul(4, 'To develop comprehensive result processing and analytics modules that automatically calculate scores, percentages, pass/fail status, and generate visual performance reports using Chart.js.'),
    nbul(5, 'To provide role-based authentication and authorization for administrators and students with secure session management.'),
    nbul(6, 'To implement PDF report generation for student report cards and administrative reports using FPDF library.'),
    nbul(7, 'To create a responsive, user-friendly interface using Bootstrap 5 that is accessible on desktop and mobile devices.'),
    nbul(8, 'To develop notification management, feedback collection, contact messaging, and webcam-based attendance verification features.'),
    nbul(9, 'To ensure the system is deployable on standard XAMPP environments without requiring specialized infrastructure or paid software licenses.'),
    nbul(10, 'To provide a scalable architecture that can be extended with additional features such as predictive analytics, mobile applications, and cloud deployment.'),
  ],
};

// CHAPTER 3
const ch3 = {
  children: [
    h1('3. Requirement Analysis'),
    h2('3.1 Functional Requirements'),
    para('The functional requirements of the system are categorized by user role:'),
    para([{ text: 'Administrator Functional Requirements:', bold: true }]),
    bul('Login to the system using email and password authentication'),
    bul('Dashboard with statistical overview of students, subjects, questions, exams, results, and feedback'),
    bul('Student management: Add, edit, search, and delete student records with course and semester information'),
    bul('Subject management: Add, edit, search, and delete subjects with unique subject codes'),
    bul('Question bank management: Add, edit, delete, search, and filter questions with difficulty levels (Easy, Medium, Hard), language selection (English, Hindi, Gujarati), marks assignment, and keyword tagging'),
    bul('AI-powered question generation via Groq API with language and difficulty customization'),
    bul('Examination management: Create and edit exams with title, subject, date, duration, pass marks, status, exam mode (Standard or Adaptive), and question selection'),
    bul('Standard mode: Manual assignment of questions from the question bank'),
    bul('Adaptive mode: AI-powered dynamic question selection with configurable maximum questions'),
    bul('Results management: View aggregated student results with search, filter, and PDF export'),
    bul('Reports and analytics: Comprehensive performance dashboards with Chart.js visualizations, pass/fail analysis, subject-wise performance, difficulty-wise accuracy, monthly attempt trends, AI-powered insights, and per-student drill-down with attempt history'),
    bul('AI Insights Dashboard: Summary statistics, risk alerts, skill category distribution, and subject performance charts'),
    bul('Feedback management, contact messages, attendance tracking, notification management, and profile settings'),
    para([{ text: 'Student Functional Requirements:', bold: true }]),
    bul('Self-registration with name, email, phone, password, course, and semester details'),
    bul('Login using registered email and password'),
    bul('Dashboard with personalized statistics, performance overview, subject-wise charts, and quick actions'),
    bul('View and start available examinations from the exam listing page'),
    bul('Standard examination: Timed interface with question palette, save/next, mark for review, clear response, auto-save, fullscreen mode, and auto-submit on timer expiry'),
    bul('Adaptive examination: AJAX-driven interface with real-time theta tracking, difficulty visualization, and AI-generated questions'),
    bul('View examination results with detailed analytics, AI performance analysis, and answer review'),
    bul('Performance analysis dashboard with overall percentage, grade, subject-wise and difficulty-wise analysis, and trend charts'),
    bul('Comprehensive report card with aggregate statistics, AI insights, and PDF export'),
    bul('Account settings with profile update, password change, and profile photo management'),
    bul('Feedback submission, contact form, and notification viewing'),
    h2('3.2 Non-Functional Requirements'),
    bul('Performance: Pages must load within 3 seconds on standard broadband connections'),
    bul('Availability: System must be accessible 24/7 via web browser'),
    bul('Security: Authentication required for all protected pages; session-based authorization; input sanitization'),
    bul('Scalability: Support multiple concurrent users taking examinations simultaneously'),
    bul('Responsiveness: Fully responsive across desktop, tablet, and mobile devices'),
    bul('Compatibility: Works on Chrome, Firefox, Edge, and Safari browsers'),
    bul('Reliability: Accurate examination submission recording under concurrent access'),
    bul('Usability: Intuitive interface with clear navigation, breadcrumb trails, and consistent design'),
    h2('3.3 Hardware Requirements'),
    mkTable(
      ['Component', 'Minimum Configuration'],
      [['Processor', 'Intel Core i3 or equivalent'], ['RAM', '4 GB or higher'], ['Storage', '500 MB free disk space'], ['Server', 'System capable of running XAMPP/WAMP'], ['Client', 'Device with modern web browser'], ['Network', '100 Mbps LAN/Wi-Fi']],
      [4000, 6000]
    ),
    h2('3.4 Software Requirements'),
    mkTable(
      ['Software', 'Version / Specification'],
      [['Operating System', 'Windows 10/11 / Linux (Ubuntu 20.04+)'], ['Web Server', 'Apache 2.4+ (via XAMPP)'], ['PHP', 'PHP 8.0 or higher'], ['Database', 'MySQL 5.7+ / MariaDB 10.4+'], ['Web Browser', 'Chrome 90+, Firefox 88+, Edge 90+, Safari 14+'], ['AI API', 'Groq API (Llama 3.3-70B) with internet connectivity']],
      [4000, 6000]
    ),
    h2('3.5 User Requirements'),
    para('The student user requires the ability to register, login, view available examinations, take examinations (both standard and adaptive), view results, analyze performance through dashboards and charts, download report cards, submit feedback, manage account settings, and communicate via the contact form.'),
    h2('3.6 Administrator Requirements'),
    para('The administrator requires comprehensive control over the entire examination lifecycle including student management, subject management, question bank management with AI generation, examination creation and configuration, result viewing and analysis, detailed reporting and analytics with AI-powered insights, feedback review, contact message management, attendance tracking, notification management, and profile maintenance.'),
  ],
};

// CHAPTER 4
const ch4 = {
  children: [
    h1('4. System Design'),
    h2('4.1 Overall Architecture'),
    para('The system follows a three-tier client-server architecture comprising the Presentation Tier, Business Logic Tier, and Data Access Tier.'),
    para([{ text: 'Presentation Tier:', bold: true }]),
    para('The presentation layer is built using HTML5, CSS3, Bootstrap 5, JavaScript, Chart.js, and SweetAlert2. It handles all user interface rendering, client-side form validation, responsive layout management, interactive chart visualization, confirmation dialogs, and AJAX communication with the server.'),
    para([{ text: 'Business Logic Tier:', bold: true }]),
    para('The business logic layer is implemented in Core PHP and handles authentication, authorization, session management, examination logic, result calculation, AI integration, data validation, PDF generation, and notification management. This layer is organized into modular components:'),
    bul('Adaptive Engine (app/adaptive/): AbilityEstimator, QuestionSelector, AdaptiveSession classes implementing IRT-based adaptive testing'),
    bul('AI Services (app/ai/): QuestionGenerator, PerformanceAnalyzer, SkillCategorizer, DashboardInsights classes integrating with Groq API'),
    bul('Helper Library (app/helpers/): Authentication, validation, database functions, GroqClient for API communication'),
    bul('Configuration (app/config/): Database connection, session configuration, constants, AI configuration'),
    h2('4.1.1 Module Communication'),
    para('Modules communicate through a combination of PHP includes, session variables, database queries, and AJAX calls. The adaptive examination module communicates asynchronously with the server through the adaptive_ajax.php endpoint, which orchestrates interactions between the AbilityEstimator, QuestionSelector, and AdaptiveSession classes. AI modules are loaded on-demand and communicate with the Groq API via the GroqClient HTTP client.'),
    para('The architecture follows: Browser (HTML/CSS/JS) \u2194 Apache Server (PHP) \u2194 MySQL Database, with PHP \u2192 cURL \u2192 Groq API (Llama 3.3-70B) for AI services.'),
    h2('4.2 UML Diagrams'),
    h3('4.2.1 Use Case Diagram'),
    para('The system supports two primary actors: Administrator and Student. The Administrator can manage students, subjects, questions, exams, results, reports, feedback, contact messages, attendance, notifications, and profile settings. The Student can register, login, take standard and adaptive exams, view results, analyze performance, download report cards, submit feedback, send contact messages, and manage account settings.'),
    h3('4.2.2 Activity Diagram'),
    para('The primary activity flow for a student examination begins with login, followed by viewing available examinations. The student selects an exam (standard or adaptive), starts the examination, answers questions within the time limit, submits the examination, and views the result with AI-powered performance analysis. The administrator activity flow begins with login, followed by managing subjects, adding questions (manually or via AI generation), creating examinations, monitoring results, and generating reports.'),
    h3('4.2.3 Class Diagram'),
    para('The system\'s object-oriented components include the GroqClient class hierarchy (GroqClient, GroqChatEndpoint, GroqCompletionsEndpoint) for AI API communication, the adaptive examination classes (AbilityEstimator, QuestionSelector, AdaptiveSession) implementing IRT-based adaptive testing logic, and the AI service classes (QuestionGenerator, PerformanceAnalyzer, SkillCategorizer, DashboardInsights).'),
    h3('4.2.4 Sequence Diagram'),
    para('The adaptive examination sequence involves: Student browser sends AJAX start request \u2192 Server creates adaptive session \u2192 Browser requests first question \u2192 Server selects question based on theta value \u2192 Student submits answer via AJAX \u2192 Server updates theta, saves answer, returns next question \u2192 Cycle continues until termination conditions met \u2192 Server calculates results, generates AI insights, saves final result \u2192 Redirect to result page.'),
    h3('4.2.5 Entity-Relationship Diagram'),
    para('The database comprises fourteen tables with key relationships:'),
    bul('subjects has one-to-many with questions (subject_id FK)'),
    bul('subjects has one-to-many with exams (subject_id FK)'),
    bul('exams has many-to-many with questions through exam_questions junction table'),
    bul('students has one-to-many with results (student_id FK)'),
    bul('exams has one-to-many with results (exam_id FK)'),
    bul('results has one-to-one with adaptive_sessions through session_id'),
    bul('students has one-to-many with feedback (student_id FK)'),
    bul('adaptive_sessions has one-to-many with adaptive_answers (session_id FK)'),

    h2('4.3 Data Dictionary'),
    para('The database named "ai_adaptive_exam" contains the following tables:'),

    bp('Table 4.1: ', 'admin'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Admin identifier'], ['name', 'VARCHAR(100)', 'NOT NULL', 'Admin name'], ['email', 'VARCHAR(100)', 'NOT NULL, UNIQUE', 'Admin email'], ['password', 'VARCHAR(255)', 'NOT NULL', 'Admin password']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.2: ', 'students'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Student identifier'], ['name', 'VARCHAR(100)', 'NOT NULL', 'Student name'], ['email', 'VARCHAR(100)', 'NOT NULL, UNIQUE', 'Student email'], ['phone', 'VARCHAR(15)', 'NOT NULL', 'Phone number'], ['password', 'VARCHAR(255)', 'NOT NULL', 'Student password'], ['course', 'VARCHAR(80)', 'NOT NULL', 'Course name'], ['semester', 'VARCHAR(30)', 'NOT NULL', 'Current semester'], ['profile_image', 'VARCHAR(255)', 'DEFAULT NULL', 'Profile photo path'], ['status', 'VARCHAR(20)', 'DEFAULT Active', 'Account status'], ['created_at', 'TIMESTAMP', 'DEFAULT CURRENT_TIMESTAMP', 'Creation date']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.3: ', 'subjects'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Subject identifier'], ['subject_name', 'VARCHAR(100)', 'NOT NULL, UNIQUE', 'Subject name'], ['subject_code', 'VARCHAR(30)', 'NOT NULL, UNIQUE', 'Subject code'], ['description', 'TEXT', 'NULL', 'Subject description'], ['status', 'VARCHAR(20)', 'DEFAULT Active', 'Subject status']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.4: ', 'questions'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Question identifier'], ['subject_id', 'INT', 'FK, NOT NULL', 'Reference to subjects.id'], ['question', 'TEXT', 'NOT NULL', 'Question text'], ['language', 'VARCHAR(40)', 'DEFAULT English', 'Question language'], ['option_a-d', 'VARCHAR(255)', 'NOT NULL', 'Options A through D'], ['correct_answer', 'CHAR(1)', 'NOT NULL', 'Correct option'], ['difficulty', 'VARCHAR(20)', 'NOT NULL', 'Easy/Medium/Hard'], ['marks', 'INT', 'DEFAULT 1', 'Marks assigned'], ['ai_generated', 'TINYINT(1)', 'DEFAULT 0', 'AI-generated flag'], ['keywords', 'VARCHAR(255)', 'NULL', 'Search keywords']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.5: ', 'exams'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Exam identifier'], ['exam_title', 'VARCHAR(150)', 'NOT NULL', 'Exam title'], ['subject_id', 'INT', 'FK, NOT NULL', 'Reference to subjects.id'], ['exam_date', 'DATE', 'NOT NULL', 'Scheduled date'], ['duration', 'INT', 'NOT NULL', 'Duration in minutes'], ['total_marks', 'INT', 'NOT NULL', 'Total marks (Standard)'], ['pass_marks', 'INT', 'NOT NULL', 'Passing marks'], ['status', 'VARCHAR(20)', 'DEFAULT Active', 'Exam status'], ['exam_mode', 'VARCHAR(20)', 'DEFAULT Standard', 'Standard/Adaptive'], ['max_questions', 'INT', 'DEFAULT 10', 'Max questions (Adaptive)']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.6: ', 'exam_questions'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Identifier'], ['exam_id', 'INT', 'FK, NOT NULL', 'Reference to exams.id'], ['question_id', 'INT', 'FK, NOT NULL', 'Reference to questions.id']],
      [2500, 2500, 2500, 2500]),
    br(),

    bp('Table 4.7: ', 'results'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Result identifier'], ['student_id', 'INT', 'FK, NOT NULL', 'Reference to students.id'], ['exam_id', 'INT', 'FK, NOT NULL', 'Reference to exams.id'], ['session_id', 'INT', 'NULL', 'Reference to adaptive_sessions.id'], ['total_questions', 'INT', 'NOT NULL', 'Total questions'], ['correct_answers', 'INT', 'NOT NULL', 'Correct count'], ['wrong_answers', 'INT', 'NOT NULL', 'Wrong count'], ['marks_obtained', 'INT', 'NOT NULL', 'Marks obtained'], ['percentage', 'DECIMAL(5,2)', 'NOT NULL', 'Percentage score'], ['status', 'VARCHAR(20)', 'NOT NULL', 'Pass/Fail/Abandoned'], ['time_spent_seconds', 'INT', 'DEFAULT 0', 'Time taken'], ['ai_insight', 'TEXT', 'NULL', 'AI performance analysis'], ['skill_category', 'VARCHAR(20)', 'NULL', 'Weak/Moderate/Advanced'], ['theta_score', 'DECIMAL(5,2)', 'NULL', 'Final theta score'], ['difficulty_breakdown', 'JSON', 'NULL', 'Per-difficulty data'], ['answers_detail', 'LONGTEXT', 'NULL', 'Answer review JSON']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.8: ', 'student_answers'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Identifier'], ['student_id', 'INT', 'FK, NOT NULL', 'Reference to students.id'], ['exam_id', 'INT', 'FK, NOT NULL', 'Reference to exams.id'], ['question_id', 'INT', 'FK, NOT NULL', 'Reference to questions.id'], ['selected_answer', 'CHAR(1)', 'NOT NULL', 'Student selected option'], ['correct_answer', 'CHAR(1)', 'NOT NULL', 'Correct option'], ['is_correct', 'TINYINT(1)', 'NOT NULL', 'Correctness flag']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.9: ', 'feedback'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Identifier'], ['student_id', 'INT', 'FK, NOT NULL', 'Reference to students.id'], ['message', 'TEXT', 'NOT NULL', 'Feedback message'], ['rating', 'INT', 'NOT NULL', 'Rating score'], ['created_at', 'TIMESTAMP', 'DEFAULT CURRENT_TIMESTAMP', 'Creation time']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.10: ', 'adaptive_sessions'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Session identifier'], ['student_id', 'INT', 'FK, NOT NULL', 'Reference to students.id'], ['exam_id', 'INT', 'FK, NOT NULL', 'Reference to exams.id'], ['theta_value', 'DECIMAL(5,2)', 'DEFAULT 0.00', 'Ability estimate'], ['current_difficulty', 'VARCHAR(20)', 'DEFAULT Medium', 'Current difficulty'], ['questions_asked', 'INT', 'DEFAULT 0', 'Questions asked'], ['correct_count', 'INT', 'DEFAULT 0', 'Correct answers'], ['wrong_count', 'INT', 'DEFAULT 0', 'Wrong answers'], ['is_completed', 'TINYINT(1)', 'DEFAULT 0', 'Completion flag']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.11: ', 'adaptive_answers'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Identifier'], ['session_id', 'INT', 'FK, NOT NULL', 'Reference to adaptive_sessions.id'], ['question_id', 'INT', 'FK, NOT NULL', 'Reference to questions.id'], ['student_answer', 'CHAR(1)', 'NULL', 'Student answer'], ['is_correct', 'TINYINT(1)', 'DEFAULT 0', 'Correctness flag'], ['time_taken_seconds', 'INT', 'DEFAULT 0', 'Time taken'], ['difficulty_at_time', 'VARCHAR(20)', 'NULL', 'Difficulty when asked']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.12: ', 'notifications'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Identifier'], ['user_type', 'VARCHAR(20)', 'NOT NULL', 'Target user type'], ['user_id', 'INT', 'NULL', 'Specific user or NULL'], ['title', 'VARCHAR(150)', 'NOT NULL', 'Notification title'], ['message', 'TEXT', 'NOT NULL', 'Notification message'], ['link', 'VARCHAR(255)', 'DEFAULT #', 'Related page link'], ['is_read', 'TINYINT(1)', 'DEFAULT 0', 'Read status']],
      [2200, 2000, 2800, 3000]),
    br(),

    bp('Table 4.13: ', 'contact_messages'),
    mkTable(['Column', 'Data Type', 'Constraints', 'Description'],
      [['id', 'INT', 'PRIMARY KEY, AUTO_INCREMENT', 'Identifier'], ['name', 'VARCHAR(100)', 'NOT NULL', 'Sender name'], ['email', 'VARCHAR(120)', 'NOT NULL', 'Sender email'], ['subject', 'VARCHAR(150)', 'NOT NULL', 'Message subject'], ['message', 'TEXT', 'NOT NULL', 'Message body'], ['reply', 'TEXT', 'NULL', 'Admin reply'], ['status', 'VARCHAR(20)', 'DEFAULT Unread', 'Message status']],
      [2200, 2000, 2800, 3000]),

    h2('4.4 Adaptive Examination Algorithm'),
    para('The adaptive examination engine implements a simplified Item Response Theory (IRT) algorithm based on a three-parameter logistic (3PL) model approximation. The algorithm operates through the following sequence:'),
    para([{ text: 'Initialization:', bold: true }]),
    para('The student ability parameter (theta) is initialized to 0.0, representing average ability. The starting difficulty is set to "Medium". A guessing probability of 0.25 is assumed for four-option multiple-choice questions.'),
    para([{ text: 'Question Selection:', bold: true }]),
    para('For each question, the system evaluates the available question bank and selects a question with difficulty nearest to the current theta estimate. The QuestionSelector class queries the database for questions matching the required difficulty, excluding previously asked questions. If the bank lacks questions, the QuestionGenerator creates an AI-generated question via the Groq API.'),
    para([{ text: 'Ability Estimation:', bold: true }]),
    para('After each answer, the AbilityEstimator updates theta using: probability_correct = 0.25 + 0.75 * (1 / (1 + exp(-(theta - difficulty)))). The adjustment is: adjustment = (correct ? 1 : -1) * max(0.1, 1 - probability_correct) * 0.5.'),
    para([{ text: 'Difficulty Mapping:', bold: true }]),
    para('Theta values map to difficulty: theta < -0.8 is "Easy", theta > 0.8 is "Hard", intermediate values are "Medium". Skill categories: theta > 1.2 is "Advanced", theta >= -0.3 is "Moderate", below -0.3 is "Weak".'),
    para([{ text: 'Termination Conditions:', bold: true }]),
    para('The adaptive session terminates when: maximum questions reached, question bank exhausted, |theta| > 1.8 after 5+ questions, theta stabilization (average change < 0.15 over last 3 questions after 8+ questions), or absolute maximum of 50 questions.'),
  ],
};

// CHAPTER 5
const ch5 = {
  children: [
    h1('5. Experimental Results'),
    para('This chapter presents the screenshots of the implemented system showcasing the key modules and user interfaces. Each screenshot is accompanied by a descriptive caption.'),
    para([{ text: '[Note: Actual screenshots should be captured from the running application and inserted here. The following descriptions indicate what each figure should contain.]', italics: true }]),
    h2('5.1 Login Interface'),
    para([{ text: 'Figure 5.1: ', bold: true }, { text: 'Login Page - The unified login interface allows administrators and students to authenticate by selecting their role, entering email and password. Includes validation, role selection dropdown, student registration link, and default admin credentials display.' }]),
    h2('5.2 Student Registration'),
    para([{ text: 'Figure 5.2: ', bold: true }, { text: 'Student Registration Page - Self-registration interface for new students with fields for name, email, phone, password, course, and semester information.' }]),
    h2('5.3 Admin Dashboard'),
    para([{ text: 'Figure 5.3: ', bold: true }, { text: 'Admin Dashboard - Central administration interface with statistical cards, welcome hero section, performance progress bars, Chart.js visualizations, quick action buttons, and recent activities table.' }]),
    h2('5.4 Student Dashboard'),
    para([{ text: 'Figure 5.4: ', bold: true }, { text: 'Student Dashboard - Personalized dashboard showing student information, exam statistics, subject-wise charts, performance doughnut chart, history line chart, and quick action links.' }]),
    h2('5.5 Manage Subjects'),
    para([{ text: 'Figure 5.5: ', bold: true }, { text: 'Subject Management - Add, edit, search, and delete subjects with name, code, description, and status fields.' }]),
    h2('5.6 Manage Questions'),
    para([{ text: 'Figure 5.6: ', bold: true }, { text: 'Question Bank Management - Comprehensive interface with subject cards, advanced filtering, paginated table, and add/edit modals with all question fields.' }]),
    h2('5.7 AI Question Generation'),
    para([{ text: 'Figure 5.7: ', bold: true }, { text: 'AI Question Generation - Interface for generating questions via Groq API with subject, topic, difficulty, language (English/Hindi/Gujarati), and count specification.' }]),
    h2('5.8 Create Standard Exam'),
    para([{ text: 'Figure 5.8: ', bold: true }, { text: 'Create Standard Exam - Examination creation form with Standard/Adaptive mode selection, subject cards, and question selection modal.' }]),
    h2('5.9 Standard Examination Interface'),
    para([{ text: 'Figure 5.9: ', bold: true }, { text: 'Standard Exam Interface - Full-screen timed interface with question display, option selectors, color-coded question palette, navigation buttons, timer countdown, and progress indicator.' }]),
    h2('5.10 Adaptive Examination Interface'),
    para([{ text: 'Figure 5.10: ', bold: true }, { text: 'Adaptive Exam Interface - Dynamic interface with real-time theta score, difficulty badge, progress bar, sidebar with stats, question palette, and AI-generated question tags.' }]),
    h2('5.11 Examination Results'),
    para([{ text: 'Figure 5.11: ', bold: true }, { text: 'Result Analytics Page - Comprehensive view with hero section, stat cards, AI performance analysis, Chart.js visualizations, and answer review modal.' }]),
    h2('5.12 Performance Analysis'),
    para([{ text: 'Figure 5.12: ', bold: true }, { text: 'Student Performance Dashboard - Overall percentage, grade, trend charts, answer split, subject-wise and difficulty-wise performance tables.' }]),
    h2('5.13 AI Insights Dashboard'),
    para([{ text: 'Figure 5.13: ', bold: true }, { text: 'AI Insights Dashboard - AI-generated system summary, risk alerts, skill distribution charts, subject performance, and exam mode distribution.' }]),
    h2('5.14 Reports and Analytics'),
    para([{ text: 'Figure 5.14: ', bold: true }, { text: 'Admin Reports and Analytics - Dashboard cards, AI summary, advanced filters, Chart.js visualizations, and student performance table with expandable rows.' }]),
    h2('5.15 PDF Report Export'),
    para([{ text: 'Figure 5.15: ', bold: true }, { text: 'PDF Report Card - Generated PDF with student information, performance summary, per-exam breakdown, and AI insights.' }]),
    h2('5.16 Feedback and Contact'),
    para([{ text: 'Figure 5.16: ', bold: true }, { text: 'Feedback and Contact Forms - Student feedback submission with rating and contact form with name, email, subject, and message.' }]),
    h2('5.17 Account Settings'),
    para([{ text: 'Figure 5.17: ', bold: true }, { text: 'Student Account Settings - Profile management with photo upload, password change, and login history.' }]),
  ],
};

// CHAPTER 6
const ch6 = {
  children: [
    h1('6. Testing and Validation'),
    para('Rigorous testing was conducted throughout the development lifecycle to ensure the system meets functional and non-functional requirements. The testing strategy encompassed unit testing, integration testing, system testing, and user acceptance testing.'),
    h2('6.1 Unit Testing'),
    para('Unit testing was performed on individual components:'),
    bul('AbilityEstimator: Theta update calculations verified with known inputs and expected outputs'),
    bul('QuestionSelector: Database queries tested with various difficulty and exclusion criteria'),
    bul('GroqClient: API communication tested with mock responses and error handling'),
    bul('Validation functions: Input sanitization tested with special characters and SQL injection patterns'),
    h2('6.2 Integration Testing'),
    para('Integration testing verified component interactions:'),
    bul('Adaptive examination flow: Session creation, question fetching, answer submission, theta update, result calculation'),
    bul('AI question generation: End-to-end flow from API request to database storage'),
    bul('Authentication and authorization: Login, session, and protected page access for both roles'),
    bul('Examination submission: Answer processing, result calculation, and notification generation'),
    h2('6.3 System Testing'),
    para('System-level testing validated complete workflows:'),
    bul('Complete examination lifecycle: Admin creates subject/adds questions/creates exam \u2192 student registers/logs in/takes exam/views result/views performance'),
    bul('Adaptive examination lifecycle: Admin creates adaptive exam \u2192 student takes adaptive exam with theta tracking \u2192 exam ends \u2192 result with AI insight generated'),
    bul('PDF generation: Report cards and exports verified for correct data and formatting'),
    h2('6.4 Test Cases'),
    para([{ text: 'Table 6.1: Test Cases Summary', bold: true }]),
    mkTable(
      ['Test ID', 'Test Case', 'Input', 'Expected Output', 'Actual Output', 'Status'],
      [
        ['TC001', 'Admin Login', 'admin@gmail.com, admin123', 'Redirect to dashboard', 'Redirected', 'Pass'],
        ['TC002', 'Invalid Admin Login', 'admin@gmail.com, wrong', 'Error message', 'Error shown', 'Pass'],
        ['TC003', 'Student Registration', 'Valid student data', 'Student added', 'Inserted', 'Pass'],
        ['TC004', 'Student Login', 'Valid credentials', 'Redirect to dashboard', 'Redirected', 'Pass'],
        ['TC005', 'Duplicate Email', 'Existing email', 'Error shown', 'Error shown', 'Pass'],
        ['TC006', 'Create Subject', 'Valid subject data', 'Subject added', 'Inserted', 'Pass'],
        ['TC007', 'Add Question', 'Valid question', 'Question added', 'Inserted', 'Pass'],
        ['TC008', 'AI Question Gen', 'Subject, difficulty, lang', 'Question generated', 'Generated', 'Pass'],
        ['TC009', 'Create Standard Exam', 'Exam + questions', 'Exam created', 'Created', 'Pass'],
        ['TC010', 'Create Adaptive Exam', 'Exam, mode=Adaptive', 'Adaptive exam created', 'Created', 'Pass'],
        ['TC011', 'Standard Exam Submit', 'All questions answered', 'Result calculated', 'Calculated', 'Pass'],
        ['TC012', 'Adaptive Exam Complete', 'Session finished', 'Result with theta + AI', 'Generated', 'Pass'],
        ['TC013', 'Timer Auto-Submit', 'Timer expires', 'Auto submission', 'Submitted', 'Pass'],
        ['TC014', 'Result View', 'Completed exam', 'Detailed result', 'Displayed', 'Pass'],
        ['TC015', 'PDF Export', 'Click download PDF', 'PDF downloaded', 'Downloaded', 'Pass'],
        ['TC016', 'Feedback Submission', 'Rating + message', 'Feedback saved', 'Saved', 'Pass'],
        ['TC017', 'Admin Logout', 'Click logout', 'Session destroyed', 'Logged out', 'Pass'],
        ['TC018', 'Duplicate Attempt', 'Already submitted exam', 'Error shown', 'Error shown', 'Pass'],
        ['TC019', 'Password Change', 'Old + new password', 'Password updated', 'Updated', 'Pass'],
        ['TC020', 'Search Students', 'Search by name', 'Matching results', 'Filtered', 'Pass'],
      ],
      [1000, 2200, 2400, 2200, 2200, 1000]
    ),
    h2('6.5 Validation Results'),
    para('All test cases passed successfully. The adaptive examination algorithm was validated by testing with simulated answer sequences and verifying that theta values moved in the expected direction. The AI integration was validated by confirming valid JSON responses from the Groq API. PDF generation was validated for correct data inclusion and formatting.'),
  ],
};

// CHAPTER 7
const ch7 = {
  children: [
    h1('7. Conclusion'),
    para('The AI-Based Adaptive Online Examination and Student Performance Analysis System has been successfully designed, developed, and tested. The system delivers a comprehensive online examination platform that addresses the limitations of traditional paper-based assessments through the integration of modern web technologies and artificial intelligence.'),
    para('The system successfully implements both Standard and Adaptive examination modes. The Standard mode provides a conventional fixed-question experience with features including timed delivery, question palette navigation, mark-for-review, localStorage auto-save, fullscreen mode, and automatic timer-based submission. The Adaptive mode implements a sophisticated IRT-based algorithm that dynamically adjusts question difficulty based on real-time student performance, providing a personalized assessment that converges on the student\'s true ability level.'),
    para('The AI integration via the Groq API (Llama 3.3-70B) represents significant value addition. The QuestionGenerator creates contextually relevant multiple-choice questions in English, Hindi, and Gujarati. The PerformanceAnalyzer transforms raw scores into meaningful, encouraging feedback. The SkillCategorizer classifies students into Weak, Moderate, and Advanced categories. The DashboardInsights module provides administrators with data-driven summaries and risk alerts.'),
    para('The comprehensive reporting and analytics module provides both students and administrators with rich visual representations of performance data through Chart.js. Students can track progress across multiple examinations, identify patterns by subject and difficulty, and download PDF report cards. Administrators gain institution-wide visibility with filtering, analysis, and export capabilities.'),
    h2('7.1 Benefits and Applications'),
    bp('Advantages:', ''),
    bul('Automated examination management eliminating paper-based processes'),
    bul('Immediate result calculation and feedback delivery'),
    bul('Personalized adaptive testing that accurately measures individual competency'),
    bul('AI-powered question generation reducing manual effort'),
    bul('Multi-language support for diverse educational contexts'),
    bul('Comprehensive analytics with interactive visualizations'),
    bul('Cost-effective deployment on standard XAMPP infrastructure'),
    bul('PDF report generation for offline access'),
    bul('Responsive design ensuring cross-device accessibility'),
    bp('Applications:', ''),
    bul('Educational institutions conducting internal assessments and semester examinations'),
    bul('Training organizations evaluating participant knowledge levels'),
    bul('Coaching centers conducting practice tests with adaptive difficulty'),
    bul('Universities for distance education online examinations'),
    bul('Corporate organizations for employee skill assessments and certification tests'),
    h2('7.2 Future Enhancements'),
    nbul(1, 'Advanced AI Integration: Natural language processing for essay evaluation, sentiment analysis, and predictive analytics for at-risk student identification.'),
    nbul(2, 'Predictive Analytics: Machine learning models to predict student performance based on historical data for proactive intervention.'),
    nbul(3, 'Mobile Application: Native Android and iOS applications for offline examination capability and push notifications.'),
    nbul(4, 'Cloud Deployment: Migration to AWS, Google Cloud, or Azure for improved scalability and reliability.'),
    nbul(5, 'Enhanced Notification System: Real-time email (PHPMailer), SMS (Twilio), and push notification integration.'),
    nbul(6, 'Online Proctoring: AI-based face detection, eye tracking, suspicious behavior detection, and screen recording.'),
    nbul(7, 'Performance Prediction: ML algorithms to predict future student performance and recommend personalized study plans.'),
    nbul(8, 'Multi-University Support: Architecture supporting multiple institutions with isolated data and branding.'),
    nbul(9, 'Role Expansion: Faculty/teacher roles with limited administrative capabilities for question creation and class monitoring.'),
    nbul(10, 'Question Pool Enhancement: Support for multiple-select, true/false, fill-in-the-blank, matching, and essay questions with AI evaluation.'),
    nbul(11, 'Gamification: Leaderboards, achievement badges, and progress rewards for increased student engagement.'),
    nbul(12, 'LMS Integration: APIs for integration with Moodle, Blackboard, and Canvas learning management systems.'),
  ],
};

// CHAPTER 8
const ch8 = {
  children: [
    h1('8. References'),
    para([{ text: '[1] ', bold: true }, { text: 'R. K. Hambleton, H. Swaminathan, and H. J. Rogers, "Fundamentals of Item Response Theory," Sage Publications, 1991.' }]),
    para([{ text: '[2] ', bold: true }, { text: 'D. Thissen and L. Steinberg, "A Taxonomy of Item Response Models," Psychometrika, vol. 51, no. 4, pp. 567\u2013577, 1986.' }]),
    para([{ text: '[3] ', bold: true }, { text: 'W. J. van der Linden and C. A. W. Glas, "Elements of Adaptive Testing," Springer, 2010.' }]),
    para([{ text: '[4] ', bold: true }, { text: 'S. G. Vaidya and S. S. Bhamare, "Online Examination System using PHP and MySQL," International Journal of Engineering Research and Technology, vol. 9, no. 3, pp. 1-5, 2020.' }]),
    para([{ text: '[5] ', bold: true }, { text: 'PHP Documentation, "PHP Manual," The PHP Group, 2024. [Online]. Available: https://www.php.net/manual/en/' }]),
    para([{ text: '[6] ', bold: true }, { text: 'MySQL Documentation, "MySQL 8.0 Reference Manual," Oracle Corporation, 2024. [Online]. Available: https://dev.mysql.com/doc/refman/8.0/en/' }]),
    para([{ text: '[7] ', bold: true }, { text: 'Bootstrap Team, "Bootstrap 5 Documentation," Bootstrap Contributors, 2024. [Online]. Available: https://getbootstrap.com/docs/5.3/' }]),
    para([{ text: '[8] ', bold: true }, { text: 'Chart.js Contributors, "Chart.js Documentation," 2024. [Online]. Available: https://www.chartjs.org/docs/4.4.0/' }]),
    para([{ text: '[9] ', bold: true }, { text: 'Groq Inc., "Groq API Documentation," 2024. [Online]. Available: https://console.groq.com/docs' }]),
    para([{ text: '[10] ', bold: true }, { text: 'O. Bousquet, S. Boucheron, and G. Lugosi, "Introduction to Statistical Learning Theory," in Advanced Lectures on Machine Learning, Springer, 2004, pp. 169\u2013207.' }]),
    para([{ text: '[11] ', bold: true }, { text: 'FPDF Contributors, "FPDF - Free PDF Library," 2024. [Online]. Available: http://www.fpdf.org/' }]),
    para([{ text: '[12] ', bold: true }, { text: 'M. T. H. Chi and R. W. Wylie, "The ICAP Framework: Linking Cognitive Engagement to Active Learning Outcomes," Educational Psychologist, vol. 49, no. 4, pp. 219\u2013243, 2014.' }]),
    para([{ text: '[13] ', bold: true }, { text: 'R. J. Mislevy, L. S. Steinberg, and R. G. Almond, "On the Structure of Educational Assessments," Measurement, vol. 1, no. 1, pp. 3\u201362, 2003.' }]),
    para([{ text: '[14] ', bold: true }, { text: 'M. D. Shermis and J. Burstein, "Automated Essay Scoring: A Cross-Disciplinary Perspective," Lawrence Erlbaum Associates, 2003.' }]),
    para([{ text: '[15] ', bold: true }, { text: 'A. J. Kunnan, "Evaluating Language Assessments," Routledge, 2018.' }]),
  ],
};

// =============================================
// BUILD DOCUMENT
// =============================================
async function main() {
  const doc = new Document({
    styles: {
      default: {
        document: {
          run: { font: FONT, size: BODY_SIZE },
        },
      },
    },
    sections: [
      titleSection,
      certSection,
      ackSection,
      abstractSection,
      tocSection,
      ch1,
      ch2,
      ch3,
      ch4,
      ch5,
      ch6,
      ch7,
      ch8,
    ],
  });

  const buffer = await Packer.toBuffer(doc);
  const outputDir = path.resolve(__dirname, '..', 'Report');
  if (!fs.existsSync(outputDir)) fs.mkdirSync(outputDir, { recursive: true });
  const outputPath = path.join(outputDir, 'kishan_Report.docx');
  fs.writeFileSync(outputPath, buffer);
  console.log('SUCCESS: Report generated at: ' + outputPath);
}

main().catch(err => { console.error('ERROR:', err.message || err); process.exit(1); });
