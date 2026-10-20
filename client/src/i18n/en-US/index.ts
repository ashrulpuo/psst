// All user-facing copy lives here so a second language is a new file, not a refactor.
export default {
  app: { name: "Psst" },

  nav: {
    inbox: "Inbox",
    sent: "Sent",
    settings: "Settings",
    main: "Main navigation"
  },

  login: {
    title: "Say it. Psst.",
    subtitle: "Send and receive anonymous messages. Sign in to continue.",
    google: "Continue with Google",
    apple: "Continue with Apple",
    privacy:
      "You stay anonymous to the person you message. Psst admins can see who sent what, to keep everyone safe.",
    notConfigured: "Sign-in isn't set up on this server yet.",
    failed: "We couldn't sign you in. Please try again.",
    demo: "Local demo accounts"
  },

  welcome: {
    title: "Pick your username",
    subtitle:
      "This becomes your personal link. You can't change it later, so choose well.",
    label: "Username",
    hint: "3–24 letters, numbers, dots or underscores",
    submit: "Create my link",
    preview: "Your link"
  },

  profile: {
    title: "Send {name} an anonymous message",
    subtitle: "They won't see who you are. Be kind.",
    label: "Your message",
    send: "Send message",
    signInToSend: "Sign in to send",
    signInNote: "Your draft is saved. You'll come back to it after signing in.",
    sent: "Sent",
    sentBody: "{name} will see it as an anonymous message.",
    viewConversation: "View conversation",
    sendAnother: "Send another",
    own: "This is your link",
    ownBody: "Share it to start receiving anonymous messages.",
    copyLink: "Copy my link",
    notFound: "We couldn't find that link",
    notFoundBody: "Check the spelling, or ask for a fresh link.",
    goHome: "Go to Psst"
  },

  inbox: {
    emptyTitle: "No messages yet",
    emptyBody: "Share your link and your first message will show up here.",
    emptyAction: "Copy my link",
    pick: "Select a conversation",
    pickBody: "Choose a message on the left to read and reply."
  },

  sent: {
    emptyTitle: "You haven't sent anything",
    emptyBody: "Open someone's link to send them an anonymous message."
  },

  thread: {
    back: "Back to messages",
    composerLabel: "Reply",
    send: "Send",
    sending: "Sending…",
    notSent: "Not sent",
    retry: "Retry",
    remove: "Remove",
    loadOlder: "Load earlier messages",
    blockedBanner: "You blocked this sender. They can't message you.",
    unblock: "Unblock",
    block: "Block sender",
    cannotReply: "This person isn't accepting replies from you.",
    report: "Report message",
    reportTitle: "Report this message",
    reportReason: "What's wrong with it?",
    reportSubmit: "Send report",
    reportThanks: "Thanks. We'll take a look.",
    options: "Conversation options",
    loadFailed: "We couldn't load this conversation.",
    tryAgain: "Try again",
    empty: "No messages yet."
  },

  settings: {
    title: "Settings",
    linkTitle: "Your link",
    linkBody: "Anyone with this link can send you an anonymous message.",
    copy: "Copy link",
    share: "Share link",
    copied: "Link copied",
    appearance: "Appearance",
    themeAuto: "Match device",
    themeLight: "Light",
    themeDark: "Dark",
    notifications: "Notifications",
    notificationsBody:
      "Get a heads-up when someone writes to you or replies, even when Psst is closed.",
    pushOn: "Notifications are on for this device.",
    pushOff: "Notifications are off for this device.",
    pushEnable: "Turn on notifications",
    pushDisable: "Turn off notifications",
    pushBlocked:
      "Notifications are blocked for this site. Allow them in your browser's site settings, then reload.",
    pushUnsupported: "This browser can't show notifications from Psst.",
    pushUnsupportedIos:
      "On iPhone and iPad, add Psst to your Home Screen first, then open it from there.",
    pushUnconfigured: "Notifications aren't set up on this server yet.",
    pushFailed: "We couldn't turn notifications on. Please try again.",
    previews: "Show message text in notifications",
    previewsBody:
      "Off by default, so nobody sees your messages on a locked screen.",
    account: "Account",
    signedInAs: "Signed in as {email}",
    signOut: "Sign out",
    danger: "Delete account",
    dangerBody:
      "Permanently erases your account, your link, and every conversation you're part of, including messages you sent. This can't be undone.",
    deleteOpen: "Delete my account",
    deleteTitle: "Delete your account?",
    deleteBody:
      "Everything is erased for good: your inbox, the messages you sent to others, and your link. The people you talked to lose those conversations too.",
    deleteLabel: "Type {name} to confirm",
    deleteConfirm: "Delete forever",
    deleteMismatch: "That doesn't match. Type it exactly as shown.",
    deleteFailed: "We couldn't delete your account. Please try again.",
    deleteAdmin:
      "Admin accounts can't be deleted here. Ask another admin to change your role first."
  },

  push: {
    promptTitle: "Get notified",
    promptBody: "Know straight away when someone writes to you or replies.",
    promptEnable: "Turn on",
    promptDismiss: "Not now"
  },

  errors: {
    generic: "Something went wrong. Please try again.",
    notFound: "That page doesn't exist.",
    notFoundBody: "It may have moved, or the link might be mistyped."
  }
};
