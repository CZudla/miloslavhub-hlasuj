# Hlasuj! by MiloslavHub — teacher guide

Documentation updated 8 October 2026. The live voting features described below build on the qualified 0.8.9 baseline. Organisation management and the expanded English interface belong to the unreleased development branch. This guide is not evidence that they have been deployed. Central AUTH and licence enforcement still need qualification. Homework is outside this iteration.

## 1. Prepare a subject and a lecture

Sign in with your own teacher account. Select your teaching space in **Organisations and sharing** when that development feature is enabled. Create a subject for the course or semester, then create a lecture within it. A lecture contains the ordered questions you intend to use in a lesson.

Give subjects, lectures and questions meaningful titles. Choose the subject's branding and presentation layout if needed. The teacher names shown in a subject are presentation metadata; adding a name there does not grant access to the application.

## 2. Create a question

Write the question title and answer options. Mark a correct answer to create a quiz. Select **No correct answer** for a poll. Polls collect opinions without a correct answer or competition points.

A quiz can use a voting time limit and scoring settings. Review these before starting the lesson. Keep the question and options understandable on a phone. The application does not translate your authored text when someone changes the interface language.

## 3. Add explanations and private notes

Use **Correct answer explanation** to prepare feedback for a quiz. Choose who may see it. The default keeps it with the teacher; an explanation configured for students appears after voting closes. The correct answer and explanation are not disclosed while voting is open.

Use your private teaching note for reminders. It is excluded from public voting responses. Content exports and authorised colleagues can have different access from students: review sharing and export scope before including personal or confidential information.

## 4. Arrange the lecture

Choose questions from the bank and order the selected list. Use drag and drop or the add, remove and arrow controls. Changing the order does not change the permanent QR addresses of existing questions.

Check the competition and cumulative scoring settings. A lecture and a subject have distinct totals; select the intended scope before collecting real responses. Historical results are not automatically recalculated by this development work.

## 5. Run a live lesson

Open the live controls and start the intended live or test run. Show the joining link or QR code to students. Students can join with a browser on a phone, tablet or computer, without installing an application or creating a student account.

You control when a question opens and closes. Opening a student link or changing language does not start a question. Use the question controls to move through the lesson. Repeating a question creates the next voting session; the permanent joining address remains usable.

Use test mode to rehearse. Check that you are in the intended mode before starting a real lesson. Read-only colleagues can view shared results; they cannot start, reset or close voting or delete test results.

## 6. Show results

Use the projection/results view for a shared display. The established public results and student links retain their public behaviour. Organisation access controls protect management operations; they do not turn an existing public results link into a private link.

If you enable live interim results, students may see the current distribution while voting remains open. Consider whether this could influence their answers. Correctness and configured answer explanations follow the closed-voting rules.

## 7. Review and export

Review runs in the results archive and use the result export when authorised. Result export and content transfer are different operations.

For reusable teaching material, use **Transfer content**. Select a subject, download its portable JSON file, or upload a file and review the preview. Confirming an import creates new drafts. It does not overwrite the original subject. The current writer uses format v2 and can read supported older v1 files.

Content transfer excludes student responses, accounts, organisation membership, permissions and licence entitlements. Its server checks require editing rights to the exported objects; read-only access to results does not grant content export rights. Imported material belongs to the selected teaching space, with new local IDs and links.

## 8. Work with colleagues

Use an existing colleague's teacher username in **Organisations and sharing**. Within an organisation, the colleague must be a member. Choose collaboration or read-only access. Sharing a subject grants inherited access to its lectures and their attached questions in the same space.

Only an authorised owner or organisation manager can manage membership. Owners can transfer content to an eligible teacher. A collaborating teacher cannot transfer or reshare someone else's object. Removing membership revokes that organisation's access without deleting teaching content.

See [Teachers and organisations](TEACHERS-AND-ORGANISATIONS.md) for the role rules, transfer behaviour and current licence boundary.

## 9. Choose the interface language

Students choose **Čeština / English**. Their browser remembers the choice. Teachers select the language in their WordPress profile. Question titles, options, explanations, private notes and nicknames retain their original text.

The standalone website demo has prepared Czech and English sample questions. It clearly labels 17 fictional respondents and uses separate temporary storage. Its automatic flow demonstrates the experience; a real live lesson remains teacher controlled.

## 10. Use optional AI deliberately

If the installation operator enables AI, preview the exact question and options before explicitly sending them. Remove personal information first. Check the returned suggestion for factual and language accuracy.

Applying a suggestion changes the editor. Use the ordinary WordPress save action to store it. You can disable AI for your own account. Ordinary voting works with AI disabled. Local provider fixtures test the integration flow; they do not establish the quality of a paid model response.

## 11. Privacy and support

Use pseudonyms when real names are unnecessary. Hall of Fame publication follows the configured visibility and the student's explicit choice; a nickname can still identify a person. Review retention and publication settings with the installation operator.

If a question does not open, check the selected lecture, run and teacher controls. If an action is denied, check your membership and explicit sharing grants. Changing a displayed teacher name does not fix access rights. For application support, contact **hlasuj@miloslavhub.cz** and include the affected page, time, browser and a short description. Do not send passwords, tokens or an unredacted student data export.
