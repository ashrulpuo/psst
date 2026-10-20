<script setup lang="ts">
// Pattern: Dashboard / Overview — a glance at status, no drill-down on the page itself.
import { onMounted } from "vue";
import { RouterLink } from "vue-router";
import { RefreshCw } from "@lucide/vue";
import { useOverviewStore } from "@/stores/overview";
import { timeAgo } from "@/lib/format";
import BarChart from "@/components/charts/BarChart.vue";
import StatTile from "@/components/StatTile.vue";
import Button from "@/components/ui/Button.vue";
import DataState from "@/components/ui/DataState.vue";
import PageHeader from "@/components/ui/PageHeader.vue";

const overview = useOverviewStore();
onMounted(() => void overview.load());
</script>

<template>
  <div class="grid grid-cols-[minmax(0,1fr)] gap-6">
    <PageHeader
      title="Dashboard"
      :subtitle="
        overview.data
          ? `Updated ${timeAgo(overview.data.generated_at)} · figures are cached for up to a minute`
          : 'Overview of Psst activity'
      "
    >
      <Button variant="outline" size="sm" :loading="overview.loading" @click="overview.load()"
        ><RefreshCw class="size-4" aria-hidden="true" />Refresh</Button
      >
    </PageHeader>

    <DataState
      :loading="overview.loading"
      :error="overview.error"
      :has-data="Boolean(overview.data)"
      @retry="overview.load()"
    >
      <div v-if="overview.data" class="grid gap-6">
        <section aria-label="Key figures" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <RouterLink to="/reports" class="contents text-fg no-underline">
            <StatTile
              hero
              label="Open reports"
              :value="overview.data.totals.open_reports"
              tone="danger"
              hint="Waiting for a moderator"
            />
          </RouterLink>
          <StatTile label="Users" :value="overview.data.totals.users" hint="Registered accounts" />
          <StatTile
            label="New users"
            :value="overview.data.totals.new_users_7d"
            hint="Last 7 days"
          />
          <StatTile
            label="Active users"
            :value="overview.data.totals.active_users_7d"
            hint="Signed in, last 7 days"
          />
          <StatTile
            label="Messages"
            :value="overview.data.totals.messages_24h"
            hint="Last 24 hours"
          />
          <StatTile
            label="Banned users"
            :value="overview.data.totals.banned_users"
            hint="Currently suspended"
          />
          <StatTile
            label="Conversations"
            :value="overview.data.totals.threads"
            :hint="`${overview.data.totals.messages} messages in total`"
          />
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
          <BarChart
            title="New users per day"
            unit="new users"
            :data="overview.data.series.signups"
          />
          <BarChart
            title="Messages per day"
            unit="messages"
            :data="overview.data.series.messages"
          />
        </div>
      </div>
    </DataState>
  </div>
</template>
