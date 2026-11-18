// All user-facing copy lives here so a second language is a new file, not a refactor.
export default {
  app: { name: "Psst" },

  home: {
    badge: "anonymous",
    line1: "say the thing.",
    line2: "no name attached.",
    sub: "Send a note to anyone with a Psst link — or get your own and see what people are too shy to say.",
    primary: "get my link 🚀 · free",
    secondary: "I have an account — sign in",
    privacy: "They see a nickname. Not your name.",
    sample1: "you were the best part of that meeting. just so you know 🤍",
    sample2: "coffee sometime? your talk gave me ideas.",
    sample3: "i've been trying to say this in person for weeks…"
  },

  nav: {
    you: "you",
    link: "Link",
    inbox: "Inbox",
    sent: "Sent",
    settings: "Settings",
    main: "Main navigation"
  },

  link: {
    title: "your link",
    live: "live",
    storyTitle: "story card",
    signInToChat: "Sign in to chat",
    storyBody: "9:16 · ready to post",
    storyStyle: "Story card style",
    storyShare: "Share story card",
    storySaved: "Story card saved. Post it to your story.",
    storyFailed: "Couldn't make the story card. Try again.",
    template: {
      sunset: "Sunset",
      mint: "Mint",
      ink: "Ink",
      candy: "Candy",
      paper: "Paper"
    },
    prompt: "ask me anything",
    hint: "Share this. New messages land in your inbox.",
    step1: "Step 1: Copy your link",
    step2: "Step 2: Share link on your story",
    copy: "copy link",
    share: "Share!"
  },

  login: {
    heading: "sign in to write back",
    subtitle:
      "Replies only work between accounts. Reading and sending stays free without one.",
    noteFrom: "a note is waiting for you",
    noteBody: "i've been trying to say this in person for weeks…",
    noteAnon: "Anonymous #11",
    stamp: "sign in",
    noAccount: "no account?",
    linkCta: "pick a username instead →",
    trustPassword: "no password",
    trustSpam: "no spam",
    trustDelete: "delete anytime",
    google: "Continue with Google",
    apple: "Continue with Apple",
    privacy:
      "You stay anonymous to the person you message. To keep everyone safe, Psst admins can see which account a message came from.",
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
    prompt: "ask me anything",
    subtitle: "They won't see who you are. Be kind.",
    promptsLabel: "Prompt ideas",
    prompts: [
      "say something nice 💌",
      "confess 🤫",
      "roast me 🔥",
      "a secret 🫣"
    ],
    privacyShort: "They see a nickname. Not your name.",
    emojiLabel: "Add an emoji",
    label: "Your message",
    send: "Send anonymously",
    chatOffer: "Want a reply? Sign in — they'll still only see Anonymous.",
    signInToChat: "Sign in to chat",
    getYourOwn: "Get your own Psst link",
    ownLinkTitle: "Want your own link?",
    ownLinkBody: "People message you anonymously.",
    ownLinkCta: "Get my link",
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
    title: "notes for you",
    newCount: "{n} new",
    emptyTitle: "No notes yet",
    emptyBody:
      "Share your link. The first note always feels weird — then it's fun.",
    emptyAction: "Share your link",
    getMessages: "Get messages",
    pick: "Select a conversation",
    pickBody: "Choose a message on the left to read and reply."
  },

  sent: {
    title: "sent by you",
    emptyTitle: "You haven't sent anything",
    emptyBody: "Open someone's link to send them an anonymous message."
  },

  thread: {
    hide: "Hide note",
    hidden: "Hidden. A new note brings it back.",
    guestTitle: "They're still a guest",
    noteMeta: "sent you a note",
    replyMeta: "you started this",
    youAppearAs: "you appear as {label}",
    react: "React to this note",
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
    guestSender:
      "They haven't signed in, so you can't reply yet. If they sign in, you'll be able to chat.",
    signInToReply: "Sign in to chat before you reply.",
    shareStory: "Share this note to your story",
    shareNoticeTitle: "Share to story",
    shareNotice:
      "Anyone who sees your story will be able to read this note. The card doesn't show who sent it, but the note's wording might.",
    shareContinue: "Share",
    shareCancel: "Cancel",
    shareSaved: "Story card saved. Post it to your story.",
    shareFailed: "Couldn't make the story card. Try again.",
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

  legal: {
    // Placeholder: the owner replaces <domain> with the real contact address before launch.
    // vue-i18n reads a bare "@" as a link and "<d" as HTML, so both are quoted literals.
    // Once the real address is known, replace the whole value with it, e.g. "hello{'@'}example.com".
    contact: "hello{'@'}{'<'}domain>",
    updated: "Plain-language summary. Last updated {date}.",
    back: "Back",
    contactLabel: "Questions or reports? Write to",
    footerLabel: "Legal",
    footerTerms: "Terms",
    footerPrivacy: "Privacy",

    terms: {
      title: "Terms",
      intro:
        "Psst lets you receive messages from people who don't have to give their name. These are the ground rules for using it, in plain words.",
      sections: {
        what: {
          heading: "What Psst is",
          body: "Everyone who signs in gets a public link. Anyone can open that link and send a message without an account. The person who owns the link sees the message, never the sender's name."
        },
        anonymity: {
          heading: "Who can see who you are",
          body: "The person you message never sees who you are. If you send as a guest, Psst admins cannot identify you either. If you send while signed in, Psst admins can see which account the message came from, so we can deal with abuse. If you send as a guest and later sign in on the same browser, your earlier guest messages are linked to that account, so admins could then identify you as their sender. Anonymous to the receiver does not mean untraceable in every situation, so don't send anything you would not be willing to stand behind."
        },
        yourPart: {
          heading: "What we ask of you",
          body: "Don't send threats, harassment, hate, sexual content involving minors, or anything illegal. Don't use Psst to stalk, scam or impersonate someone. Don't try to break the service or get around its limits."
        },
        sharing: {
          heading: "Sharing what you receive",
          body: "A message sent to you is yours to read. If you choose to share one, for example in a screenshot or a post, it is no longer private to you. We can't control where it goes after that."
        },
        safety: {
          heading: "Blocking and reporting",
          body: "You can block a sender. A signed-in sender will see that they can no longer message you; a blocked guest's messages are quietly dropped. You can report any message you receive. Admins can open conversations to investigate abuse, review reports and check messages our automatic word check sets aside, and each time they do it is logged."
        },
        bans: {
          heading: "Bans",
          body: "If someone abuses Psst, we may ban their account, remove their messages, or refuse their messages. Signed-in accounts can be banned. Guests have no account, so for them we can only block on a receiver's behalf, and we do that on a best-effort basis."
        },
        deleting: {
          heading: "Leaving",
          body: "You can delete your account at any time: open Settings, choose Delete my account, and type your username to confirm. This erases your account, your link and your conversations, including the messages you sent. It can't be undone. We keep one minimal record that an account was deleted, with an opaque id and no name or message text. Encrypted backups roll off within 14 days."
        },
        asIs: {
          heading: "No guarantees",
          body: "Psst is provided as it is. It may change, have bugs or go offline. We do our best, but we can't promise it will always be available."
        },
        changes: {
          heading: "Changes",
          body: "If these terms change in a way that matters, we will update this page and its date."
        }
      }
    },

    privacy: {
      title: "Privacy",
      intro:
        "This page says what Psst keeps about you and what it doesn't, without the fine print.",
      sections: {
        anonymity: {
          heading: "Anonymity, exactly",
          body: "The person you message never sees your name, email or account. Psst admins can identify a signed-in sender, because the message is tied to that account. Admins cannot identify a guest, because a guest has no account. The exception: if a guest later signs in on the same browser, their earlier guest messages are linked to the account, and admins could then identify them. We don't claim more than that."
        },
        collect: {
          heading: "What we keep",
          body: "If you sign in, we keep what your sign-in provider gives us (your name, email address and profile picture from Google, plus the provider's account id), when you last signed in, your username, your messages and conversations, your blocks and reports, and the settings you choose, such as notifications. For guests we keep the message, a marker for the browser so a reply can reach it, and a scrambled (hashed) form of the network address so a receiver's block can hold."
        },
        encryption: {
          heading: "Messages are stored encrypted",
          body: "Message text is encrypted at rest. Admins can open conversations to investigate abuse and review reports, and each access is logged. When a message is sent, it is checked automatically against a short list of words that suggest threats or self-harm. A match is set aside for an admin to read; the message is still delivered."
        },
        usage: {
          heading: "Anonymous usage counts",
          body: "We collect aggregate counts of accounts and messages, such as how many accounts exist, how many messages are sent per day and how often links and notes are shared. These counts store no account, device or network address and are kept by day only."
        },
        sharing: {
          heading: "Who we share it with",
          body: "We don't sell your data. We use service providers to run Psst, for example for hosting, sign-in, notifications and error monitoring, and they only get what they need to do that. Error reports have message text and personal details removed. We may also have to share information if the law requires it."
        },
        shared: {
          heading: "What gets shared is out of our hands",
          body: "If a receiver shows a message to someone else, for example by posting a screenshot, it is no longer private to them or to the sender."
        },
        delete: {
          heading: "Deleting your data",
          body: "Open Settings, choose Delete my account, and type your username. Your account, link and conversations, including the messages you sent, are erased. We keep one minimal record that an account was deleted, with an opaque id and no name or message text. Encrypted backups roll off within 14 days. Copies that other people already made are outside our control."
        },
        safety: {
          heading: "Blocking, reporting and bans",
          body: "If you block a signed-in sender, they will see that they can no longer message you; a blocked guest's messages are quietly dropped. Reports are reviewed by our team. Accounts that abuse Psst are banned."
        }
      }
    }
  },

  errors: {
    generic: "Something went wrong. Please try again.",
    notFound: "That page doesn't exist.",
    notFoundBody: "It may have moved, or the link might be mistyped."
  }
};
