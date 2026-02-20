-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 11, 2026 at 02:26 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `faq`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_` varchar(50) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--



-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `email`, `password_hash`, `full_name`, `created_at`, `updated_at`, `admin_image`) VALUES
(2, 'edriannnnn', 'delapenaedrian555@gmail.com', '$2y$10$23FTN0yuHCofncJqtvVivORdGDLm/0Azwe.aAlwl50SWBp60.l4Xi', 'Edrian Dela Pena', '2026-02-09 02:03:56', '2026-02-09 03:21:28', 'admin_images/admin_1770607288_698952b8b86bd.png'),
(4, 'Juan Dela Cruz', 'delapenaedrian555@gmail.comm', '$2y$10$HGnpLk/qUy8YXSBFkij1xeHpNKnRDyzP6duCVPxPk4zQoCkuqR8BC', 'Juan Dela Cruz', '2026-02-09 02:15:59', '2026-02-09 03:21:17', 'admin_images/admin_1770607277_698952add5c9d.png'),
(5, 'edrian', 'delapenaedrian555@gmail.commm', '$2y$10$YNDsYxadfAwkjAAsTspqkO9IkUCWVseX8uJuMOBSOgAboNPVa1xZy', 'Juan Dela Cruzzzzzzzz', '2026-02-09 03:16:30', '2026-02-09 03:16:30', 'admin_images/admin_1770606990_6989518e8b48e.png');
-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

CREATE TABLE `articles` (
  `article_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(100) NOT NULL,
  `article_image` varchar(255) DEFAULT NULL,
  `article_date` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `article_type` varchar(100) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `introduction` text DEFAULT NULL,
  `status` enum('Publish','Published') NOT NULL DEFAULT 'Publish',
  `admin_id` int(11)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `articles`
--

INSERT INTO `articles` (`article_id`, `title`, `content`, `category`, `article_image`, `article_date`, `created_at`, `article_type`, `subcategory_id`, `introduction`, `status`, `admin_id`) VALUES
(156, 'How to Cancel a Change of Schedule Request', 'Step 1: Navigate to the Change of Schedule Modules\n<p>After logging in, locate the&nbsp;<strong>\"Change of Schedule\"</strong>&nbsp;option in the sidebar menu under&nbsp;<strong>Applications</strong>&nbsp;and click on it.</p>\n\nStep 2: Find Your Request in \'My Requests\'\n<p>On the main page, find the request you wish to cancel in the&nbsp;<strong>\"My Requests\"</strong>&nbsp;table. Click on its row to view the full details.</p>\n\nStep 3: View Request Details and Click \'Cancel Request\'\n<p>The request details page will open. Review the&nbsp;<strong>Overview</strong>&nbsp;and&nbsp;<strong>Detailed Information</strong>. At the bottom of the page, click the&nbsp;<strong>\"Cancel Request\"</strong>&nbsp;button.</p>\n\nStep 4: Confirm the Cancellation\n<p>A confirmation dialog will appear, showing the request summary. To proceed with cancellation, click the&nbsp;<strong>\"Cancel Request\"</strong>&nbsp;button in the dialog. To keep the request, click&nbsp;<strong>\"Back\"</strong>.</p>\n\nStep 5: Request Successfully Cancelled\n<p>A success message will confirm that your COS request has been cancelled. Click&nbsp;<strong>\"Done\"</strong>. The request status in your&nbsp;<strong>\"My Requests\"</strong>&nbsp;table will now show as&nbsp;<strong>Cancelled.</strong></p>', 'Change of Schedule', '{\"1\":\"article_1770268401_698426f180c3c.png\",\"2\":\"article_1770268401_698426f1810e2.png\",\"3\":\"article_1770268401_698426f18151a.png\",\"4\":\"article_1770268401_698426f18190c.png\",\"5\":\"article_1770268401_698426f181e49.png\"}', '2026-02-09', '2026-02-05 05:13:21', 'step_by_step', 17, '<p>Follow these steps to cancel a pending Change of Schedule (COS) request that has not yet been approved.</p>', 'Published', 1),
(158, 'Who can approve my schedule change request?', 'Q: <p>Who needs to approve my Change of Schedule request after I submit it?</p><p><br></p>\n\nA: <p>All Change of Schedule requests follow a defined workflow for approval. After you submit your request, it is automatically routed to your designated approver (typically your direct supervisor or manager) within the HRDOTNET system. You can track the real-time status (e.g., \"Pending,\" \"Reviewed,\" \"Approved\") in the&nbsp;<strong>\'My Requests\'</strong>&nbsp;table and see any approval reasons in the&nbsp;<strong>Detailed Information</strong>&nbsp;section of your request.</p><p> </p><p> </p>', 'Change of Schedule', NULL, '2026-02-05', '2026-02-05 05:17:29', 'simple_question', 17, NULL, 'Publish', 1),
(159, 'Can I edit a request after it\'s approved?', 'Q: <p>Can I edit or cancel my Change of Schedule request after it has been approved?</p><p> </p><p> </p>\n\nA: <p>No. Once a Change of Schedule request has been&nbsp;<strong>approved</strong>, it is considered final and locked in the system. You can only&nbsp;<strong>edit</strong>&nbsp;or&nbsp;<strong>cancel</strong>&nbsp;a request while its status is still&nbsp;<strong>pending</strong>&nbsp;(e.g., \"Filed\" or \"For Review\"). Please review your request carefully before submitting it.</p>', 'Change of Schedule', NULL, '2026-02-05', '2026-02-05 05:17:55', 'simple_question', 17, NULL, 'Publish', 1),
(160, 'Where can I see why my request was cancelled?', 'Q: <p>I see my request was cancelled. Where can I find the reason?</p>\n\nA: <p>You can find the reason in the request details. Go to&nbsp;<strong>Change of Schedule &gt; My Requests</strong>, click on the specific request, and look in the&nbsp;<strong>Overview</strong>&nbsp;section under&nbsp;<strong>Detailed Information</strong>. The&nbsp;<strong>Cancellation Reason</strong>&nbsp;will be displayed there. You can also check the&nbsp;<strong>History</strong>&nbsp;section for a log of the action.</p><p> </p><p> </p>', 'Change of Schedule', NULL, '2026-02-05', '2026-02-05 05:18:18', 'simple_question', 17, NULL, 'Publish', 1),
(161, 'How long does approval take?', 'Q: <p>How long does it usually take for a Change of Schedule request to be approved?</p><p> </p><p> </p>\n\nA: <p>Approval times can vary depending on your department and approver\'s availability. There is no fixed system timeline. To check the status, regularly monitor your request in the&nbsp;<strong>\'My Requests\'</strong>&nbsp;table. The&nbsp;<strong>History</strong>&nbsp;section will show you the exact date and time when your approver takes action on it.</p>', 'Change of Schedule', NULL, '2026-02-05', '2026-02-05 05:18:51', 'simple_question', 17, NULL, 'Publish', 1),
(162, 'What if I make a mistake on my request?', 'Q: <p>What should I do if I submitted a Change of Schedule request with incorrect information?</p><p> </p><p> </p>\n\nA: <p>If the request is still&nbsp;<strong>pending approval</strong>, you can easily correct it yourself. Navigate to the request in&nbsp;<strong>\'My Requests\'</strong>, view its details, and click the&nbsp;<strong>\'Edit Request\'</strong>&nbsp;button to update the information. If the request has already been&nbsp;<strong>approved</strong>, you will need to contact your HR representative or system administrator for assistance, as approved requests cannot be edited by employees.</p><p> </p><p> </p>', 'Change of Schedule', NULL, '2026-02-05', '2026-02-05 05:19:21', 'simple_question', 17, NULL, 'Publish', 1),
(163, 'Understanding Change of Schedule (COS) Requests in HRDOTNET', '<p class=\"ql-align-justify\">A Change of Schedule (COS) request is a formal process within the HRDOTNET Genie system that allows you to propose temporary or permanent adjustments to your established work hours or designated workdays. This tool is essential for managing appointments, personal commitments, or adapting to shifting project needs, all while maintaining proper record-keeping and ensuring operational coverage. By using the system, you ensure that your manager and the HR department are formally notified and can plan accordingly.</p><p class=\"ql-align-justify\">To initiate a request, navigate to&nbsp;<strong>Applications &gt; Change of Schedule</strong>&nbsp;and click&nbsp;<strong>\"Add New Request.\"</strong>&nbsp;You will fill out a form detailing the new schedule, dates, and reason for the change. A critical feature is the&nbsp;<strong>\"Mark this as Rest Day\"</strong>&nbsp;option, which you must use if the adjustment involves switching a regular workday to a rest day. All requests follow a clear workflow: once submitted, they go to your approver, and you can track their status—whether&nbsp;<strong>Filed, Reviewed, Approved, or Cancelled</strong>—in your personal&nbsp;<strong>\'My Requests\'</strong>&nbsp;dashboard.</p><p class=\"ql-align-justify\">It is important to manage your requests proactively. You have the ability to&nbsp;<strong>view, edit, or cancel</strong>&nbsp;a request as long as its status remains&nbsp;<strong>pending</strong>. Once approved, the change is locked. For a complete record, each request page provides an&nbsp;<strong>Overview</strong>&nbsp;of details, an&nbsp;<strong>Attachments</strong>&nbsp;section for supporting documents, and a&nbsp;<strong>History</strong>&nbsp;log. For any future reference, you can also download a report of the request using the&nbsp;<strong>Download</strong>&nbsp;button located at the top of the page.</p>', 'Change of Schedule', NULL, '2026-02-05', '2026-02-05 05:20:02', 'standard', 17, NULL, 'Publish', 1),
(164, 'How to Request Leave', 'Step 1: Navigate to the Leave Module\n<p>After logging in, locate the&nbsp;<strong>\"Leave\"</strong>&nbsp;option in the sidebar menu under Applications. Click on it to proceed. To file a new request, click the&nbsp;<strong>\"New Request\"</strong>&nbsp;button.</p>\n\nStep 2: Select Leave Type and Dates\n<p>A form will open. First, select the&nbsp;<strong>Type of Leave</strong>&nbsp;(e.g., Vacation, Sick, Emergency) from the dropdown menu. Then, choose your&nbsp;<strong>Start Date</strong>&nbsp;and&nbsp;<strong>End Date</strong>.&nbsp;The system will automatically calculate the number of days requested.</p>\n\nStep 3: Complete Details and Review\n<p>Next, enter your reason for leave and attach any required documents. Double-check all entered information, especially the dates and leave type. Ensure your leave balance is sufficient. Once verified, click the&nbsp;<strong>\"Submit\"</strong>&nbsp;button at the bottom of the form.</p>\n\nStep 4: Final Confirmation\n<p>A confirmation dialog will appear, summarizing your request. Review the details one final time. If correct, click&nbsp;<strong>\"Confirm Submit\"</strong>&nbsp;to proceed. If you need to make changes, click&nbsp;<strong>\"Back.\"</strong></p>\n\nStep 5: Request Successfully Filed\n<p>A success message will confirm your Leave request has been submitted. Click&nbsp;<strong>\"OK\"</strong>&nbsp;or&nbsp;<strong>\"Done.\"</strong>&nbsp;You can now track its approval status in the&nbsp;<strong>\"My Leave Requests\"</strong>&nbsp;section of the dashboard.</p>', 'Leave', '{\"1\":\"article_1770269116_698429bc12e0f.png\",\"2\":\"article_1770269116_698429bc13303.png\",\"3\":\"article_1770269116_698429bc137d3.png\",\"4\":\"article_1770269116_698429bc13de1.png\",\"5\":\"article_1770269116_698429bc1419a.png\"}', '2026-02-05', '2026-02-05 05:25:16', 'step_by_step', 18, NULL, 'Publish', 1),
(165, 'How to Edit a Leave Request', 'Step 1: Navigate to the Leave Module\n<p>After logging in, locate the&nbsp;<strong>\"Leave\"</strong>&nbsp;option in the sidebar menu under Applications and click on it.</p>\n\nStep 2: Find Your Request in \'My Leave Requests\'\n<p>On the main page, locate the&nbsp;<strong>\"My Leave Requests\"</strong>&nbsp;table. Find and click on the specific request row you wish to edit to view its full details.</p>\n\nStep 3: View Request Details and Click \'Edit\'\n<p>The request details page will open, showing an Overview and the full information. At the bottom of this page, click the&nbsp;<strong>\"Edit Request\"</strong>&nbsp;button.</p>\n\nStep 4: Make Your Changes\n<p>The leave request form will open in edit mode. Update the necessary fields (such as dates, leave type, or reason). Once all changes are made, click the&nbsp;<strong>\"Update\"</strong>&nbsp;button.</p>\n\nStep 5: iew Your Updated Request\n<p>A confirmation dialog will appear, displaying a summary of your updated leave request. Review all details carefully. If correct, click&nbsp;<strong>\" Update\"</strong>. If you need to make more changes, click&nbsp;<strong>\"Back\"</strong>&nbsp;to edit again.</p>\n\nStep 6: Confirmation of Update\n<p>A success message will confirm that your Leave request has been updated successfully. Click&nbsp;<strong>\"Done\"</strong>&nbsp;to complete the process. The updated request will now appear in your&nbsp;<strong>\"My Leave Requests\"</strong>&nbsp;table.</p>', 'Leave', '{\"1\":\"article_1770269311_69842a7fc1286.png\",\"2\":\"article_1770269311_69842a7fc17a9.png\",\"3\":\"article_1770269311_69842a7fc1bfe.png\",\"4\":\"article_1770269311_69842a7fc1fe3.png\",\"5\":\"article_1770269311_69842a7fc2468.png\",\"6\":\"article_1770269311_69842a7fc27fe.p', '2026-02-05', '2026-02-05 05:28:31', 'step_by_step', 18, NULL, 'Publish', 1),
(166, 'How to Cancel a Leave Request', 'Step 1: Navigate to the Leave Module\n<p>After logging in, locate the&nbsp;<strong>\"Leave\"</strong>&nbsp;option in the sidebar menu under Applications and click on it.</p>\n\nStep 2: Find Your Request in \'My Leave Requests\'\n<p>On the main page, find the request you wish to cancel in the&nbsp;<strong>\"My Leave Requests\"</strong>&nbsp;table. Click on its row to view the full details.</p>\n\nStep 3: View Request Details and Click \'Cancel Request\'\n<p>The request details page will open. Review the Overview and Detailed Information. At the bottom of the page, click the&nbsp;<strong>\"Cancel Request\"</strong>&nbsp;button.</p>\n\nStep 4: Confirm the Cancellation\n<p>A confirmation dialog will appear, showing the request summary. To proceed with cancellation, click the&nbsp;<strong>\"Cancel Request\"</strong>&nbsp;button in the dialog. To keep the request, click&nbsp;<strong>\"Back\"</strong>.</p>\n\nStep 5: Request Successfully Cancelled\n<p>A success message will confirm that your Leave request has been cancelled. Click&nbsp;<strong>\"Done\"</strong>. The request status in your&nbsp;<strong>\"My Leave Requests\"</strong>&nbsp;table will now show as&nbsp;<strong>Cancelled</strong>.</p>', 'Leave', '{\"1\":\"article_1770269467_69842b1b8f3b6.png\",\"2\":\"article_1770269467_69842b1b8f79f.png\",\"3\":\"article_1770269467_69842b1b8fbcd.png\",\"4\":\"article_1770269467_69842b1b90074.png\",\"5\":\"article_1770269467_69842b1b90461.png\"}', '2026-02-05', '2026-02-05 05:31:07', 'step_by_step', 18, NULL, 'Publish', 1),
(168, 'How do I check my leave balance?', 'Q: <p>Where can I see how many leave days I have left?</p><p> </p><p> </p>\n\nA: <p>After logging into HRDOTNET Genie, navigate to the Leave module. Your available leave balances for each type (e.g., Vacation, Sick) are typically displayed on the main dashboard or in the \"Leave Balance\" section of the application.</p>', 'Leave', NULL, '2026-02-05', '2026-02-05 05:34:37', 'simple_question', 18, NULL, 'Publish', 1),
(169, 'What should I do if my leave request is rejected?', 'Q: <p>My manager rejected my leave request. What are the next steps? </p>\n\nA: <p>First, review the rejection remarks in the History or Detailed Information section of the request. You may need to clarify your reason, adjust the dates, or provide additional documentation. You can then create and submit a new, corrected leave request.</p>', 'Leave', NULL, '2026-02-05', '2026-02-05 05:35:08', 'simple_question', 18, NULL, 'Publish', 1),
(170, 'Can I attach multiple documents to my leave request?', 'Q: <p>Am I allowed to upload more than one file (like a doctor\'s note and a travel ticket) with my request?</p>\n\nA: <p>Yes, the HRDOTNET Genie system allows you to attach multiple documents. In the \"Complete Details and Review\" step, use the attachment field to browse and select all necessary files before submitting your request.</p>', 'Leave', NULL, '2026-02-05', '2026-02-05 05:35:33', 'simple_question', 18, NULL, 'Publish', 1),
(171, 'Understanding Your Leave Types and Benefits', '<p class=\"ql-align-justify\">Taking time off is essential for maintaining health, well-being, and productivity. The HRDOTNET Genie system provides a streamlined way to manage various types of approved leave. Understanding your options is the first step to planning effectively. The most common leave types include&nbsp;<strong>Vacation Leave</strong>&nbsp;for personal rest and recreation,&nbsp;<strong>Sick Leave</strong>&nbsp;for medical needs, and&nbsp;<strong>Emergency Leave</strong>&nbsp;for unforeseen personal or family matters.</p><p class=\"ql-align-justify\">Each leave type has specific policies and may require different levels of documentation. For instance, Sick Leave for more than three days often requires a medical certificate, while Emergency Leave may need a brief explanation. Properly utilizing your leave not only ensures you get the time off you need but also helps in accurate record-keeping and compliance with company policies. Planning your leave in advance when possible also allows for smoother workflow transitions and team coordination.</p><p class=\"ql-align-justify\">Managing your leave effectively contributes to a better work-life balance and prevents burnout. Always check your available leave balance in the system before filing a request. Remember, timely and accurate leave requests facilitate faster approvals from your manager and the HR department, ensuring a hassle-free process for everyone involved.</p>', 'Leave', NULL, '2026-02-05', '2026-02-05 05:36:17', 'standard', 18, NULL, 'Publish', 1),
(172, 'The Leave Request Lifecycle: From Filing to Approval ', '<p class=\"ql-align-justify\">When you submit a leave request in HRDOTNET Genie, it enters a structured workflow designed for transparency and efficiency. Knowing this lifecycle helps you track progress and understand the necessary steps for a successful approval. The journey typically begins when you, as the employee,&nbsp;<strong>file a new request</strong>&nbsp;by selecting your leave type, dates, and providing the required details. Once submitted, the request status changes to \"<strong>For Approval</strong>,\" indicating it has been routed to your immediate supervisor or manager for review.</p><p class=\"ql-align-justify\">The next critical stage is the&nbsp;<strong>manager\'s review</strong>. At this point, your manager assesses the request against team schedules, project deadlines, and company policy. They can either approve it, which moves it forward, or deny it, which requires them to provide a reason. An approved request is often then endorsed to the&nbsp;<strong>Human Resources (HR) department</strong>&nbsp;for final validation against your leave credits and policy compliance before it is officially granted.</p><p class=\"ql-align-justify\">Once fully approved, the status updates to \"<strong>Approved</strong>\" in your&nbsp;<strong>My Leave Requests</strong>&nbsp;dashboard, and your leave balance is automatically deducted. If a request is&nbsp;<strong>cancelled</strong>&nbsp;(by you) or&nbsp;<strong>denied</strong>&nbsp;(by a reviewer), the status clearly reflects this outcome, and the history log provides a record of the action. Understanding this end-to-end process ensures you know what to expect after hitting \"submit\" and empowers you to plan accordingly.</p>', 'Leave', NULL, '2026-02-05', '2026-02-05 05:36:47', 'standard', 18, NULL, 'Publish', 1),
(181, 'juan', 'Q: <p>sdfsed</p>\n\nA: <p>dcsd</p>', 'Change of Schedule', NULL, '2026-02-09', '2026-02-09 07:53:56', 'simple_question', 17, NULL, 'Publish', 1),
(182, 'qwerty', 'Q: <p>dfgdhhf</p>\n\nA: <p>fsfsesegfe</p>', 'Change of Schedule', NULL, '2026-02-09', '2026-02-09 07:56:02', 'simple_question', 17, NULL, 'Publish', 1),
(183, 'anne', 'Q: <p>dssd</p>\n\nA: <p>dssdgsd</p>', 'Change of Schedule', NULL, '2026-02-09', '2026-02-09 08:48:46', 'simple_question', 17, NULL, 'Published', 1),
(184, 'qwertyuiop', 'Q: <p>xdgh</p>\n\nA: <p>dgdg</p>', 'Change of Schedule', NULL, '2026-02-10', '2026-02-10 01:24:03', 'simple_question', 17, NULL, 'Published', 1);

-- --------------------------------------------------------

--
-- Table structure for table `article_attachments`
--

CREATE TABLE `article_attachments` (
  `attachment_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `article_feedback`
--

CREATE TABLE `article_feedback` (
  `rating_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `is_helpful` tinyint(1) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `article_feedback`
--

INSERT INTO `article_feedback` (`rating_id`, `article_id`, `is_helpful`, `admin_id`, `created_at`) VALUES
(17, 158, 1, 16, '2026-02-05 05:55:45'),
(18, 170, 1, 16, '2026-02-05 06:45:28'),
(19, 169, 1, 16, '2026-02-05 06:45:34'),
(20, 168, 1, 16, '2026-02-05 06:45:39'),
(21, 161, 1, 16, '2026-02-05 06:45:50'),
(22, 163, 1, 16, '2026-02-05 07:20:50'),
(25, 159, 1, 16, '2026-02-05 08:00:22'),
(26, 159, 0, 22, '2026-02-09 05:38:34');

-- --------------------------------------------------------

--
-- Table structure for table `article_qa`
--

CREATE TABLE `article_qa` (
  `article_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `article_qa`
--

INSERT INTO `article_qa` (`article_id`, `question`, `answer`) VALUES
(158, '<p>Who needs to approve my Change of Schedule request after I submit it?</p><p><br></p>', '<p>All Change of Schedule requests follow a defined workflow for approval. After you submit your request, it is automatically routed to your designated approver (typically your direct supervisor or manager) within the HRDOTNET system. You can track the real-time status (e.g., \"Pending,\" \"Reviewed,\" \"Approved\") in the&nbsp;<strong>\'My Requests\'</strong>&nbsp;table and see any approval reasons in the&nbsp;<strong>Detailed Information</strong>&nbsp;section of your request.</p><p> </p><p> </p>'),
(159, '<p>Can I edit or cancel my Change of Schedule request after it has been approved?</p><p> </p><p> </p>', '<p>No. Once a Change of Schedule request has been&nbsp;<strong>approved</strong>, it is considered final and locked in the system. You can only&nbsp;<strong>edit</strong>&nbsp;or&nbsp;<strong>cancel</strong>&nbsp;a request while its status is still&nbsp;<strong>pending</strong>&nbsp;(e.g., \"Filed\" or \"For Review\"). Please review your request carefully before submitting it.</p>'),
(160, '<p>I see my request was cancelled. Where can I find the reason?</p>', '<p>You can find the reason in the request details. Go to&nbsp;<strong>Change of Schedule &gt; My Requests</strong>, click on the specific request, and look in the&nbsp;<strong>Overview</strong>&nbsp;section under&nbsp;<strong>Detailed Information</strong>. The&nbsp;<strong>Cancellation Reason</strong>&nbsp;will be displayed there. You can also check the&nbsp;<strong>History</strong>&nbsp;section for a log of the action.</p><p> </p><p> </p>'),
(161, '<p>How long does it usually take for a Change of Schedule request to be approved?</p><p> </p><p> </p>', '<p>Approval times can vary depending on your department and approver\'s availability. There is no fixed system timeline. To check the status, regularly monitor your request in the&nbsp;<strong>\'My Requests\'</strong>&nbsp;table. The&nbsp;<strong>History</strong>&nbsp;section will show you the exact date and time when your approver takes action on it.</p>'),
(162, '<p>What should I do if I submitted a Change of Schedule request with incorrect information?</p><p> </p><p> </p>', '<p>If the request is still&nbsp;<strong>pending approval</strong>, you can easily correct it yourself. Navigate to the request in&nbsp;<strong>\'My Requests\'</strong>, view its details, and click the&nbsp;<strong>\'Edit Request\'</strong>&nbsp;button to update the information. If the request has already been&nbsp;<strong>approved</strong>, you will need to contact your HR representative or system administrator for assistance, as approved requests cannot be edited by employees.</p><p> </p><p> </p>'),
(168, '<p>Where can I see how many leave days I have left?</p><p> </p><p> </p>', '<p>After logging into HRDOTNET Genie, navigate to the Leave module. Your available leave balances for each type (e.g., Vacation, Sick) are typically displayed on the main dashboard or in the \"Leave Balance\" section of the application.</p>'),
(169, '<p>My manager rejected my leave request. What are the next steps? </p>', '<p>First, review the rejection remarks in the History or Detailed Information section of the request. You may need to clarify your reason, adjust the dates, or provide additional documentation. You can then create and submit a new, corrected leave request.</p>'),
(170, '<p>Am I allowed to upload more than one file (like a doctor\'s note and a travel ticket) with my request?</p>', '<p>Yes, the HRDOTNET Genie system allows you to attach multiple documents. In the \"Complete Details and Review\" step, use the attachment field to browse and select all necessary files before submitting your request.</p>'),
(181, '<p>sdfsed</p>', '<p>dcsd</p>'),
(182, '<p>dfgdhhf</p>', '<p>fsfsesegfe</p>'),
(183, '<p>dssd</p>', '<p>dssdgsd</p>'),
(184, '<p>xdgh</p>', '<p>dgdg</p>');

-- --------------------------------------------------------

--
-- Table structure for table `article_standard`
--

CREATE TABLE `article_standard` (
  `article_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `standard_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `article_standard`
--

INSERT INTO `article_standard` (`article_id`, `description`, `standard_image`) VALUES
(163, '<p class=\"ql-align-justify\">A Change of Schedule (COS) request is a formal process within the HRDOTNET Genie system that allows you to propose temporary or permanent adjustments to your established work hours or designated workdays. This tool is essential for managing appointments, personal commitments, or adapting to shifting project needs, all while maintaining proper record-keeping and ensuring operational coverage. By using the system, you ensure that your manager and the HR department are formally notified and can plan accordingly.</p><p class=\"ql-align-justify\">To initiate a request, navigate to&nbsp;<strong>Applications &gt; Change of Schedule</strong>&nbsp;and click&nbsp;<strong>\"Add New Request.\"</strong>&nbsp;You will fill out a form detailing the new schedule, dates, and reason for the change. A critical feature is the&nbsp;<strong>\"Mark this as Rest Day\"</strong>&nbsp;option, which you must use if the adjustment involves switching a regular workday to a rest day. All requests follow a clear workflow: once submitted, they go to your approver, and you can track their status—whether&nbsp;<strong>Filed, Reviewed, Approved, or Cancelled</strong>—in your personal&nbsp;<strong>\'My Requests\'</strong>&nbsp;dashboard.</p><p class=\"ql-align-justify\">It is important to manage your requests proactively. You have the ability to&nbsp;<strong>view, edit, or cancel</strong>&nbsp;a request as long as its status remains&nbsp;<strong>pending</strong>. Once approved, the change is locked. For a complete record, each request page provides an&nbsp;<strong>Overview</strong>&nbsp;of details, an&nbsp;<strong>Attachments</strong>&nbsp;section for supporting documents, and a&nbsp;<strong>History</strong>&nbsp;log. For any future reference, you can also download a report of the request using the&nbsp;<strong>Download</strong>&nbsp;button located at the top of the page.</p>', 'article_1770268802_698428820c6cb.png'),
(171, '<p class=\"ql-align-justify\">Taking time off is essential for maintaining health, well-being, and productivity. The HRDOTNET Genie system provides a streamlined way to manage various types of approved leave. Understanding your options is the first step to planning effectively. The most common leave types include&nbsp;<strong>Vacation Leave</strong>&nbsp;for personal rest and recreation,&nbsp;<strong>Sick Leave</strong>&nbsp;for medical needs, and&nbsp;<strong>Emergency Leave</strong>&nbsp;for unforeseen personal or family matters.</p><p class=\"ql-align-justify\">Each leave type has specific policies and may require different levels of documentation. For instance, Sick Leave for more than three days often requires a medical certificate, while Emergency Leave may need a brief explanation. Properly utilizing your leave not only ensures you get the time off you need but also helps in accurate record-keeping and compliance with company policies. Planning your leave in advance when possible also allows for smoother workflow transitions and team coordination.</p><p class=\"ql-align-justify\">Managing your leave effectively contributes to a better work-life balance and prevents burnout. Always check your available leave balance in the system before filing a request. Remember, timely and accurate leave requests facilitate faster approvals from your manager and the HR department, ensuring a hassle-free process for everyone involved.</p>', 'article_1770269777_69842c519f9ff.png'),
(172, '<p class=\"ql-align-justify\">When you submit a leave request in HRDOTNET Genie, it enters a structured workflow designed for transparency and efficiency. Knowing this lifecycle helps you track progress and understand the necessary steps for a successful approval. The journey typically begins when you, as the employee,&nbsp;<strong>file a new request</strong>&nbsp;by selecting your leave type, dates, and providing the required details. Once submitted, the request status changes to \"<strong>For Approval</strong>,\" indicating it has been routed to your immediate supervisor or manager for review.</p><p class=\"ql-align-justify\">The next critical stage is the&nbsp;<strong>manager\'s review</strong>. At this point, your manager assesses the request against team schedules, project deadlines, and company policy. They can either approve it, which moves it forward, or deny it, which requires them to provide a reason. An approved request is often then endorsed to the&nbsp;<strong>Human Resources (HR) department</strong>&nbsp;for final validation against your leave credits and policy compliance before it is officially granted.</p><p class=\"ql-align-justify\">Once fully approved, the status updates to \"<strong>Approved</strong>\" in your&nbsp;<strong>My Leave Requests</strong>&nbsp;dashboard, and your leave balance is automatically deducted. If a request is&nbsp;<strong>cancelled</strong>&nbsp;(by you) or&nbsp;<strong>denied</strong>&nbsp;(by a reviewer), the status clearly reflects this outcome, and the history log provides a record of the action. Understanding this end-to-end process ensures you know what to expect after hitting \"submit\" and empowers you to plan accordingly.</p>', 'article_1770269807_69842c6f90078.png');

-- --------------------------------------------------------

--
-- Table structure for table `article_steps`
--

CREATE TABLE `article_steps` (
  `step_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `step_number` int(11) NOT NULL,
  `step_title` varchar(255) NOT NULL,
  `step_description` text NOT NULL,
  `step_image` varchar(255) DEFAULT NULL,
  `introduction` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `article_steps`
--

INSERT INTO `article_steps` (`step_id`, `article_id`, `step_number`, `step_title`, `step_description`, `step_image`, `introduction`) VALUES
(224, 164, 1, 'Navigate to the Leave Module', '<p>After logging in, locate the&nbsp;<strong>\"Leave\"</strong>&nbsp;option in the sidebar menu under Applications. Click on it to proceed. To file a new request, click the&nbsp;<strong>\"New Request\"</strong>&nbsp;button.</p>', 'article_1770269116_698429bc12e0f.png', '<p>This guide will walk you through the step-by-step process of filing a Leave request in HRDOTNET Genie.</p>'),
(225, 164, 2, 'Select Leave Type and Dates', '<p>A form will open. First, select the&nbsp;<strong>Type of Leave</strong>&nbsp;(e.g., Vacation, Sick, Emergency) from the dropdown menu. Then, choose your&nbsp;<strong>Start Date</strong>&nbsp;and&nbsp;<strong>End Date</strong>.&nbsp;The system will automatically calculate the number of days requested.</p>', 'article_1770269116_698429bc13303.png', '<p>This guide will walk you through the step-by-step process of filing a Leave request in HRDOTNET Genie.</p>'),
(226, 164, 3, 'Complete Details and Review', '<p>Next, enter your reason for leave and attach any required documents. Double-check all entered information, especially the dates and leave type. Ensure your leave balance is sufficient. Once verified, click the&nbsp;<strong>\"Submit\"</strong>&nbsp;button at the bottom of the form.</p>', 'article_1770269116_698429bc137d3.png', '<p>This guide will walk you through the step-by-step process of filing a Leave request in HRDOTNET Genie.</p>'),
(227, 164, 4, 'Final Confirmation', '<p>A confirmation dialog will appear, summarizing your request. Review the details one final time. If correct, click&nbsp;<strong>\"Confirm Submit\"</strong>&nbsp;to proceed. If you need to make changes, click&nbsp;<strong>\"Back.\"</strong></p>', 'article_1770269116_698429bc13de1.png', '<p>This guide will walk you through the step-by-step process of filing a Leave request in HRDOTNET Genie.</p>'),
(228, 164, 5, 'Request Successfully Filed', '<p>A success message will confirm your Leave request has been submitted. Click&nbsp;<strong>\"OK\"</strong>&nbsp;or&nbsp;<strong>\"Done.\"</strong>&nbsp;You can now track its approval status in the&nbsp;<strong>\"My Leave Requests\"</strong>&nbsp;section of the dashboard.</p>', 'article_1770269116_698429bc1419a.png', '<p>This guide will walk you through the step-by-step process of filing a Leave request in HRDOTNET Genie.</p>'),
(229, 165, 1, 'Navigate to the Leave Module', '<p>After logging in, locate the&nbsp;<strong>\"Leave\"</strong>&nbsp;option in the sidebar menu under Applications and click on it.</p>', 'article_1770269311_69842a7fc1286.png', '<p>This guide explains how to locate and modify an existing, editable Leave request before it is approved.</p>'),
(230, 165, 2, 'Find Your Request in \'My Leave Requests\'', '<p>On the main page, locate the&nbsp;<strong>\"My Leave Requests\"</strong>&nbsp;table. Find and click on the specific request row you wish to edit to view its full details.</p>', 'article_1770269311_69842a7fc17a9.png', '<p>This guide explains how to locate and modify an existing, editable Leave request before it is approved.</p>'),
(231, 165, 3, 'View Request Details and Click \'Edit\'', '<p>The request details page will open, showing an Overview and the full information. At the bottom of this page, click the&nbsp;<strong>\"Edit Request\"</strong>&nbsp;button.</p>', 'article_1770269311_69842a7fc1bfe.png', '<p>This guide explains how to locate and modify an existing, editable Leave request before it is approved.</p>'),
(232, 165, 4, 'Make Your Changes', '<p>The leave request form will open in edit mode. Update the necessary fields (such as dates, leave type, or reason). Once all changes are made, click the&nbsp;<strong>\"Update\"</strong>&nbsp;button.</p>', 'article_1770269311_69842a7fc1fe3.png', '<p>This guide explains how to locate and modify an existing, editable Leave request before it is approved.</p>'),
(233, 165, 5, 'iew Your Updated Request', '<p>A confirmation dialog will appear, displaying a summary of your updated leave request. Review all details carefully. If correct, click&nbsp;<strong>\" Update\"</strong>. If you need to make more changes, click&nbsp;<strong>\"Back\"</strong>&nbsp;to edit again.</p>', 'article_1770269311_69842a7fc2468.png', '<p>This guide explains how to locate and modify an existing, editable Leave request before it is approved.</p>'),
(234, 165, 6, 'Confirmation of Update', '<p>A success message will confirm that your Leave request has been updated successfully. Click&nbsp;<strong>\"Done\"</strong>&nbsp;to complete the process. The updated request will now appear in your&nbsp;<strong>\"My Leave Requests\"</strong>&nbsp;table.</p>', 'article_1770269311_69842a7fc27fe.png', '<p>This guide explains how to locate and modify an existing, editable Leave request before it is approved.</p>'),
(235, 166, 1, 'Navigate to the Leave Module', '<p>After logging in, locate the&nbsp;<strong>\"Leave\"</strong>&nbsp;option in the sidebar menu under Applications and click on it.</p>', 'article_1770269467_69842b1b8f3b6.png', '<p>Follow these steps to cancel a pending Leave request that has not yet been approved.</p>'),
(236, 166, 2, 'Find Your Request in \'My Leave Requests\'', '<p>On the main page, find the request you wish to cancel in the&nbsp;<strong>\"My Leave Requests\"</strong>&nbsp;table. Click on its row to view the full details.</p>', 'article_1770269467_69842b1b8f79f.png', '<p>Follow these steps to cancel a pending Leave request that has not yet been approved.</p>'),
(237, 166, 3, 'View Request Details and Click \'Cancel Request\'', '<p>The request details page will open. Review the Overview and Detailed Information. At the bottom of the page, click the&nbsp;<strong>\"Cancel Request\"</strong>&nbsp;button.</p>', 'article_1770269467_69842b1b8fbcd.png', '<p>Follow these steps to cancel a pending Leave request that has not yet been approved.</p>'),
(238, 166, 4, 'Confirm the Cancellation', '<p>A confirmation dialog will appear, showing the request summary. To proceed with cancellation, click the&nbsp;<strong>\"Cancel Request\"</strong>&nbsp;button in the dialog. To keep the request, click&nbsp;<strong>\"Back\"</strong>.</p>', 'article_1770269467_69842b1b90074.png', '<p>Follow these steps to cancel a pending Leave request that has not yet been approved.</p>'),
(239, 166, 5, 'Request Successfully Cancelled', '<p>A success message will confirm that your Leave request has been cancelled. Click&nbsp;<strong>\"Done\"</strong>. The request status in your&nbsp;<strong>\"My Leave Requests\"</strong>&nbsp;table will now show as&nbsp;<strong>Cancelled</strong>.</p>', 'article_1770269467_69842b1b90461.png', '<p>Follow these steps to cancel a pending Leave request that has not yet been approved.</p>'),
(326, 156, 1, 'Navigate to the Change of Schedule Modules', '<p>After logging in, locate the&nbsp;<strong>\"Change of Schedule\"</strong>&nbsp;option in the sidebar menu under&nbsp;<strong>Applications</strong>&nbsp;and click on it.</p>', 'article_1770268401_698426f180c3c.png', ''),
(327, 156, 2, 'Find Your Request in \'My Requests\'', '<p>On the main page, find the request you wish to cancel in the&nbsp;<strong>\"My Requests\"</strong>&nbsp;table. Click on its row to view the full details.</p>', 'article_1770268401_698426f1810e2.png', ''),
(328, 156, 3, 'View Request Details and Click \'Cancel Request\'', '<p>The request details page will open. Review the&nbsp;<strong>Overview</strong>&nbsp;and&nbsp;<strong>Detailed Information</strong>. At the bottom of the page, click the&nbsp;<strong>\"Cancel Request\"</strong>&nbsp;button.</p>', 'article_1770268401_698426f18151a.png', ''),
(329, 156, 4, 'Confirm the Cancellation', '<p>A confirmation dialog will appear, showing the request summary. To proceed with cancellation, click the&nbsp;<strong>\"Cancel Request\"</strong>&nbsp;button in the dialog. To keep the request, click&nbsp;<strong>\"Back\"</strong>.</p>', 'article_1770268401_698426f18190c.png', ''),
(330, 156, 5, 'Request Successfully Cancelled', '<p>A success message will confirm that your COS request has been cancelled. Click&nbsp;<strong>\"Done\"</strong>. The request status in your&nbsp;<strong>\"My Requests\"</strong>&nbsp;table will now show as&nbsp;<strong>Cancelled.</strong></p>', 'article_1770268401_698426f181e49.png', '');

-- --------------------------------------------------------

--
-- Table structure for table `article_tags`
--

CREATE TABLE `article_tags` (
  `article_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `article_tags`
--

INSERT INTO `article_tags` (`article_id`, `tag_id`) VALUES
(156, 37),
(156, 38),
(156, 39),
(159, 37),
(159, 41),
(159, 44),
(169, 41),
(169, 43),
(169, 45),
(181, 43),
(181, 46),
(181, 48),
(182, 43),
(182, 46),
(182, 48),
(183, 43),
(183, 46),
(183, 48),
(184, 43),
(184, 46),
(184, 48);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description_` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description_`, `created_by`, `created_at`, `category_image`) VALUES
(56, 'Employee Self Service (ESS)', 'Your personal dashboard for managing HR-related tasks. This section covers all guides for requesting time-related adjustments, viewing pay information, and updating your personal data through the HRDOTNET system.', 5, '2026-02-05 04:29:24', 'uploads/categories/69841ca471921_1770265764.png'),
(60, 'fsef', 'sefsef', 5, '2026-02-09 03:42:31', 'uploads/categories/698957a785e9a_1770608551.png');

-- --------------------------------------------------------

--
-- Table structure for table `google_oauth_users`
--

CREATE TABLE `google_oauth_users` (
  `oauth_id` int(11) NOT NULL,
  `google_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `name` varchar(255) DEFAULT NULL,
  `avatar` varchar(500) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `approved` tinyint(1) DEFAULT 0,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `google_oauth_users`
--

INSERT INTO `google_oauth_users` (`oauth_id`, `google_id`, `email`, `user_name`, `created_at`, `updated_at`, `name`, `avatar`, `department`, `password_hash`, `approved`, `registration_date`) VALUES
(16, '115173953543051921264', 'bello.jemcreydel.bsit@gmail.com', 'Jem Creydel Bello', '2026-01-29 06:56:59', '2026-01-29 06:56:59', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJQRuVwvYygJmD57KbMCbX8esOePy5_WVWEfH9LuoDs4W_FM_0=s96-c', 'HR', '$2y$10$8h9D.VQqwtETmb1XHE.F.eqK0DMVu9xDIoMldcCXgAdP6MZkRMFQm', 1, '2026-01-29 06:56:59'),
(17, '103310084386866456157', 'jemcreydelbello@gmail.com', 'Jem Creydel Bello', '2026-01-29 07:49:31', '2026-01-29 07:49:31', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJN6QVEm3oxFIi24L-HsxW_d7VgJUvIslaAA4e46pIx-q3DBQni=s96-c', 'HR', '$2y$10$tOzPw6cfwma8SgIxRCENGuF26uPnApSD85M8lcE5nBi7KMUg0QYJi', 1, '2026-01-29 07:49:31'),
(18, '105351959213118860363', 'delapenaedrian555@gmail.com', 'Edrian Dela Peña', '2026-02-02 01:58:41', '2026-02-02 01:58:41', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocLavpgx_Zj2Ql_mni7zQEqOKc0TcCD5F5VFTop3iuXVrC8XLkqo=s96-c', 'Logistics', '$2y$10$ykKOyZQtaxefbqbQVahm5eZP5EPlvYzUH5RRmZsewjFjDrNWyIKp.', 1, '2026-02-02 01:58:41'),
(19, '112283570407817433217', 'delapena.edrian.bsit@gmail.com', 'Edrian Dela Peña', '2026-02-02 02:48:49', '2026-02-02 02:48:49', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJUCfTNedeEAR1gZ3rH0ovY0n3CfiADsSXw57kh5NFYeOH7=s96-c', 'Administration', '$2y$10$DIm5wEvXgZ4j85tg7mlbNOUk6isBYUlOExNK.jdIYUubEw1UTmRGG', 1, '2026-02-02 02:48:49'),
(20, '113936049176637379967', 'yey055440@gmail.com', 'yey ey', '2026-02-04 07:28:41', '2026-02-04 07:28:41', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocI6nao6TCzTLujMMZzR692Pf6_lKYHfiLGur4Hu6Tu-ArbX=s96-c', 'IT', '$2y$10$hne5ui7ixKtCEnLXF2JPueGKN0VytaYNmRQqQGbiHGRgrnJnpfNCG', 1, '2026-02-04 07:28:41'),
(21, '103488538663543484387', 'we555tuna@gmail.com', 'we we', '2026-02-09 02:07:59', '2026-02-09 02:07:59', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocKEIbO9Rt8r04nhyIWQGQ8mnVq4EuyvDYJGTVNkToShvm2WDg=s96-c', 'Administration', '$2y$10$T6aCSz3RS.znsctXgbuQJ.vEQGzxTDfPUuSCJ2eXID0H/1ZQm9ISa', 1, '2026-02-09 02:07:59'),
(22, '107592811910955684240', 'henessy528@gmail.com', 'Henessy', '2026-02-09 05:37:26', '2026-02-09 05:37:26', NULL, 'https://lh3.googleusercontent.com/a/ACg8ocJxf4MeOVx29g7UjnniUs0gBoU4CVEdrn4MRFAEOyF_SZ4LpA=s96-c', 'IT', '$2y$10$NM.CO60Xt4av1KUrdjy/vegVM/WyLuUk8MB7ZhGjfgNhunAXBlaQ2', 1, '2026-02-09 05:37:26');

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `subcategory_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_name` varchar(255) NOT NULL,
  `description_` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subcategories`
--

INSERT INTO `subcategories` (`subcategory_id`, `category_id`, `subcategory_name`, `description_`, `created_by`, `created_at`) VALUES
(17, 56, 'Change of Schedule', 'Articles related to requesting temporary or permanent changes to your standard working hours or workdays.', 1, '2026-02-05 04:29:53'),
(18, 56, 'Leave', 'Guides for applying for, canceling, and tracking different types of approved time off (e.g., Vacation, Sick, Bereavement Leave).', 1, '2026-02-05 04:30:03'),
(19, 56, 'Official Business', 'Instructions for filing and obtaining approval for periods when you are working off-site for company-related duties.', 1, '2026-02-05 04:30:13'),
(20, 56, 'Missed Log', 'Step-by-step solutions for correcting your daily attendance record if you forget to clock in or out.', 1, '2026-02-05 04:30:26'),
(21, 56, 'Overtime', 'Articles on how to properly request, report, and get approval for overtime work.', 1, '2026-02-05 04:30:44'),
(22, 56, 'Offset', 'Information on the policy and process for offsetting undertime hours by working extra hours on another scheduled day.', 1, '2026-02-05 04:30:58'),
(23, 56, 'Compensatory Time Off', 'Guides on how to earn and use Compensatory Time Off, which is paid time off granted in lieu of overtime pay.', 1, '2026-02-05 04:31:10'),
(24, 60, 'fef', 'wfwef', 5, '2026-02-09 03:42:39');

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `tag_id` int(11) NOT NULL,
  `tag_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`tag_id`, `tag_name`) VALUES
(46, 'approval time'),
(39, 'cancel request'),
(48, 'document upload'),
(36, 'edit request'),
(43, 'employee guide'),
(45, 'leave request'),
(49, 'leave types'),
(47, 'policy guide'),
(42, 'request details'),
(41, 'request history'),
(37, 'schedule change'),
(44, 'time off'),
(38, 'update form'),
(50, 'vdfv'),
(40, 'view request');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `ticket_id` int(11) NOT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `subject_` varchar(200) NOT NULL,
  `description_` text DEFAULT NULL,
  `client_name` varchar(120) DEFAULT NULL,
  `client_email` varchar(120) DEFAULT NULL,
  `status_` enum('Pending','In Progress','Done') DEFAULT 'Pending',
  `date_resolved` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attachment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`ticket_id`, `submitted_by`, `category_id`, `subject_`, `description_`, `client_name`, `client_email`, `status_`, `date_resolved`, `created_at`, `attachment`) VALUES
(56, NULL, 56, 'bvbn', 'vbnm', 'Jem Creydel Bello', 'bello.jemcreydel.bsit@gmail.com', 'In Progress', NULL, '2026-02-05 07:35:39', NULL),
(57, NULL, 56, 'wdwer', 'fwerfwef', 'Edrian Dela Peña', 'delapena.edrian.bsit@gmail.com', 'Done', '2026-02-09 00:00:00', '2026-02-09 03:29:27', 'C:\\xampp\\htdocs\\FAQ\\client/../uploads/tickets/ticket_1770607767_698954970663a.png'),
(58, NULL, 56, 'wdwer', 'fwerfwef', 'Edrian Dela Peña', 'delapena.edrian.bsit@gmail.com', 'In Progress', NULL, '2026-02-09 03:39:36', 'C:\\xampp\\htdocs\\FAQ\\client/../uploads/tickets/ticket_1770608376_698956f81e20a.png');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`article_id`),
  ADD KEY `articles_ibfk_2` (`subcategory_id`);

--
-- Indexes for table `article_attachments`
--
ALTER TABLE `article_attachments`
  ADD PRIMARY KEY (`attachment_id`),
  ADD KEY `article_id` (`article_id`);

--
-- Indexes for table `article_feedback`
--
ALTER TABLE `article_feedback`
  ADD PRIMARY KEY (`rating_id`),
  ADD UNIQUE KEY `unique_feedback` (`article_id`,`admin_id`);

--
-- Indexes for table `article_qa`
--
ALTER TABLE `article_qa`
  ADD PRIMARY KEY (`article_id`);

--
-- Indexes for table `article_standard`
--
ALTER TABLE `article_standard`
  ADD PRIMARY KEY (`article_id`);

--
-- Indexes for table `article_steps`
--
ALTER TABLE `article_steps`
  ADD PRIMARY KEY (`step_id`),
  ADD KEY `article_id` (`article_id`);

--
-- Indexes for table `article_tags`
--
ALTER TABLE `article_tags`
  ADD PRIMARY KEY (`article_id`,`tag_id`),
  ADD KEY `fk_article_tags_tag` (`tag_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `google_oauth_users`
--
ALTER TABLE `google_oauth_users`
  ADD PRIMARY KEY (`oauth_id`),
  ADD UNIQUE KEY `google_id` (`google_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_google_id` (`google_id`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`subcategory_id`),
  ADD KEY `fk_subcategory_category` (`category_id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`tag_id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `idx_tickets_created_at` (`created_at`),
  ADD KEY `idx_tickets_category_id` (`category_id`),
  ADD KEY `idx_tickets_status` (`status_`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=828;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `articles`
--
ALTER TABLE `articles`
  MODIFY `article_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=185;

--
-- AUTO_INCREMENT for table `article_attachments`
--
ALTER TABLE `article_attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `article_feedback`
--
ALTER TABLE `article_feedback`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `article_steps`
--
ALTER TABLE `article_steps`
  MODIFY `step_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=331;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `google_oauth_users`
--
ALTER TABLE `google_oauth_users`
  MODIFY `oauth_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `subcategory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `tag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_2` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`subcategory_id`) ON DELETE SET NULL;

--
-- Constraints for table `article_attachments`
--
ALTER TABLE `article_attachments`
  ADD CONSTRAINT `article_attachments_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE;

--
-- Constraints for table `article_feedback`
--
ALTER TABLE `article_feedback`
  ADD CONSTRAINT `article_feedback_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`);

--
-- Constraints for table `article_qa`
--
ALTER TABLE `article_qa`
  ADD CONSTRAINT `article_qa_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE;

--
-- Constraints for table `article_standard`
--
ALTER TABLE `article_standard`
  ADD CONSTRAINT `article_standard_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE;

--
-- Constraints for table `article_steps`
--
ALTER TABLE `article_steps`
  ADD CONSTRAINT `article_steps_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE;

--
-- Constraints for table `article_tags`
--
ALTER TABLE `article_tags`
  ADD CONSTRAINT `article_tags_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`article_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `article_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`admin_id`) ON DELETE NO ACTION;

--
-- Constraints for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD CONSTRAINT `fk_subcategory_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`submitted_by`) REFERENCES `google_oauth_users` (`oauth_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
