import type { RouteRecordRaw } from "vue-router";

declare module "vue-router" {
  interface RouteMeta {
    requiresAuth?: boolean;
    guestOnly?: boolean;
    title?: string;
  }
}

const routes: RouteRecordRaw[] = [
  // Public front door. Signed-in visitors are sent on to /link by the guard.
  {
    path: "/",
    name: "home",
    component: () => import("@/pages/HomePage.vue"),
    meta: { title: "Psst" }
  },

  // Form (auth): a centred card, no app chrome.
  {
    path: "/",
    component: () => import("@/layouts/AuthLayout.vue"),
    children: [
      {
        path: "login",
        component: () => import("@/pages/LoginPage.vue"),
        meta: { guestOnly: true, title: "Sign in" }
      },
      {
        path: "get-link",
        component: () => import("@/pages/GetLinkPage.vue"),
        meta: { title: "Your link" }
      },
      // Public legal pages. Must stay above /:username so they win over a profile.
      {
        path: "terms",
        name: "terms",
        component: () => import("@/pages/TermsPage.vue"),
        meta: { title: "Terms" }
      },
      {
        path: "privacy",
        name: "privacy",
        component: () => import("@/pages/PrivacyPage.vue"),
        meta: { title: "Privacy" }
      },
      {
        path: "welcome",
        component: () => import("@/pages/WelcomePage.vue"),
        meta: { requiresAuth: true, title: "Choose a username" }
      }
    ]
  },

  // Split Pane (inbox / sent) and Settings live inside the app shell.
  {
    path: "/",
    component: () => import("@/layouts/AppLayout.vue"),
    meta: { requiresAuth: true },
    children: [
      {
        path: "link",
        name: "link",
        component: () => import("@/pages/LinkPage.vue"),
        meta: { title: "Your link" }
      },
      {
        path: "inbox/:threadId?",
        name: "inbox",
        component: () => import("@/pages/MessagesPage.vue"),
        props: { box: "inbox" },
        meta: { title: "Inbox" }
      },
      {
        path: "sent/:threadId?",
        name: "sent",
        component: () => import("@/pages/MessagesPage.vue"),
        props: { box: "sent" },
        meta: { title: "Sent" }
      },
      {
        path: "settings",
        component: () => import("@/pages/SettingsPage.vue"),
        meta: { title: "Settings" }
      }
    ]
  },

  // Public link page: /alice. Declared after the static routes above, which win by specificity.
  {
    path: "/:username([a-z0-9_.]{3,24})",
    component: () => import("@/layouts/AuthLayout.vue"),
    children: [
      {
        path: "",
        component: () => import("@/pages/ProfilePage.vue"),
        props: true
      }
    ]
  },

  {
    path: "/:catchAll(.*)*",
    component: () => import("@/pages/ErrorNotFound.vue")
  }
];

export default routes;
