<script setup lang="ts">
// Pattern: Reading page — plain-language legal text on the public auth shell.
import { useMeta } from "quasar";
import { useI18n } from "vue-i18n";

const { t } = useI18n();
useMeta({ title: `${t("legal.privacy.title")} · Psst` });

const sections = [
  "anonymity",
  "collect",
  "encryption",
  "usage",
  "sharing",
  "shared",
  "delete",
  "safety"
] as const;
</script>

<template>
  <article class="legal">
    <header class="head">
      <h1>{{ $t("legal.privacy.title") }}</h1>
      <p class="text-secondary-copy">{{ $t("legal.privacy.intro") }}</p>
      <p class="tiny">{{ $t("legal.updated") }}</p>
    </header>

    <section v-for="key in sections" :key="key" class="block">
      <h2>{{ $t(`legal.privacy.sections.${key}.heading`) }}</h2>
      <p>{{ $t(`legal.privacy.sections.${key}.body`) }}</p>
    </section>

    <p class="contact">
      {{ $t("legal.contactLabel") }}
      <a :href="`mailto:${$t('legal.contact')}`">{{ $t("legal.contact") }}</a>
    </p>
    <nav class="more" :aria-label="$t('legal.footerLabel')">
      <router-link to="/terms">{{ $t("legal.footerTerms") }}</router-link>
      <router-link to="/">{{ $t("legal.back") }}</router-link>
    </nav>
  </article>
</template>

<style scoped lang="scss">
.legal {
  display: grid;
  gap: var(--s-4);
}
.head {
  display: grid;
  gap: 6px;
}
.tiny {
  margin: 0;
  color: var(--text-3);
  font-size: 0.75rem;
}
.block {
  display: grid;
  gap: var(--s-2);
  padding: var(--s-4) var(--s-5);
  border: 2.5px solid var(--border-strong);
  border-radius: var(--r-card);
  background: var(--surface);
  box-shadow: 0 4px 0 var(--border);
}
.block h2 {
  font-size: var(--text-body);
}
.block p {
  margin: 0;
  color: var(--text-2);
  font-size: var(--text-secondary);
  line-height: 1.55;
}
.contact {
  margin: 0;
  color: var(--text-2);
  font-size: var(--text-secondary);
  overflow-wrap: anywhere;
}
.more {
  display: flex;
  justify-content: space-between;
  gap: var(--s-3);
  font-size: var(--text-secondary);
}
.more a,
.contact a {
  color: var(--accent-text, var(--accent));
}
.more a {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
}
</style>
