import { createRouter, createWebHistory, type RouteRecordRaw } from "vue-router";
import { setAuthLostHandler, type ApiError } from "@/lib/api";
import { useAuthStore, type Gate } from "@/stores/auth";

declare module "vue-router" {
  interface RouteMeta {
    /** `gate` screens sit between sign-in and the app; `app` screens need a fully verified admin. */
    kind: "gate" | "app";
    title: string;
    gate?: Gate;
  }
}

const routes: RouteRecordRaw[] = [
  {
    path: "/login",
    name: "login",
    component: () => import("@/pages/LoginPage.vue"),
    meta: { kind: "gate", gate: "guest", title: "Sign in" },
  },
  {
    path: "/denied",
    name: "denied",
    component: () => import("@/pages/DeniedPage.vue"),
    meta: { kind: "gate", gate: "not-admin", title: "Not an admin" },
  },
  {
    path: "/2fa/setup",
    name: "setup2fa",
    component: () => import("@/pages/TwoFactorSetupPage.vue"),
    meta: { kind: "gate", gate: "setup-2fa", title: "Set up two-factor" },
  },
  {
    path: "/2fa",
    name: "verify2fa",
    component: () => import("@/pages/TwoFactorVerifyPage.vue"),
    meta: { kind: "gate", gate: "verify-2fa", title: "Two-factor code" },
  },
  {
    path: "/",
    component: () => import("@/layouts/AppShell.vue"),
    meta: { kind: "app", title: "" },
    children: [
      {
        path: "",
        name: "dashboard",
        component: () => import("@/pages/DashboardPage.vue"),
        meta: { kind: "app", title: "Dashboard" },
      },
      {
        path: "users",
        name: "users",
        component: () => import("@/pages/UsersPage.vue"),
        meta: { kind: "app", title: "Users" },
      },
      {
        path: "users/:id",
        name: "user",
        component: () => import("@/pages/UserDetailPage.vue"),
        props: true,
        meta: { kind: "app", title: "User" },
      },
      {
        path: "threads",
        name: "threads",
        component: () => import("@/pages/ThreadsPage.vue"),
        meta: { kind: "app", title: "Conversations" },
      },
      {
        path: "threads/:id",
        name: "thread",
        component: () => import("@/pages/ThreadDetailPage.vue"),
        props: true,
        meta: { kind: "app", title: "Conversation" },
      },
      {
        path: "reports/:id?",
        name: "reports",
        component: () => import("@/pages/ReportsPage.vue"),
        props: true,
        meta: { kind: "app", title: "Reports" },
      },
      {
        path: "audit",
        name: "audit",
        component: () => import("@/pages/AuditPage.vue"),
        meta: { kind: "app", title: "Audit log" },
      },
    ],
  },
  { path: "/:catchAll(.*)*", redirect: "/" },
];

export const gateRoute: Record<Exclude<Gate, "ready">, string> = {
  guest: "login",
  "not-admin": "denied",
  "setup-2fa": "setup2fa",
  "verify-2fa": "verify2fa",
};

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: () => ({ left: 0, top: 0 }),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  await auth.init();
  const gate = auth.gate;

  if (gate !== "ready") {
    const target = gateRoute[gate];
    return to.name === target ? true : { name: target };
  }
  // Fully verified: the gate screens have nothing left to offer.
  return to.meta.kind === "gate" ? { name: "dashboard" } : true;
});

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Psst Admin` : "Psst Admin";
});

/** A 401, or a 403 saying the second factor is missing: re-evaluate where the session stands. */
setAuthLostHandler(async (error: ApiError) => {
  const auth = useAuthStore();
  if (error.status === 401) {
    auth.me = null;
    auth.twoFactor = null;
  } else {
    await auth.refresh();
  }
  const target = gateRoute[auth.gate as Exclude<Gate, "ready">];
  if (target && router.currentRoute.value.name !== target) await router.replace({ name: target });
});
