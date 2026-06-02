# Getting a public .ics feed URL from Google Calendar

The **Calendar Feed** block displays upcoming events from an iCalendar (`.ics`) feed. To use it with a Google Calendar, you first need to get that calendar's public `.ics` address, then paste it into the block.

This guide walks through both steps in plain language. No coding required.

> **Heads up:** The calendar has to be **public** for the block to read it. If the calendar is private, the block will not be able to load its events. Only use a calendar whose events are okay for anyone on the web to see.

---

## Part 1 — Make the calendar public

You only need to do this once per calendar.

1. Open [Google Calendar](https://calendar.google.com) on your laptop or desktop (you can't change these settings from the phone app).
2. In the list on the left under **My calendars**, hover over the calendar you want to share. Click the three dots (**⋮**) that appear, then choose **Settings and sharing**.
3. Scroll down to the **Access permissions for events** section.
4. Check the box labeled **Make available to public**.
5. Google will warn you that the calendar's events will be visible to anyone. Confirm that you want to make the calendar public.
   - Next to the checkbox, you can choose how much detail is shared. **See all event details** shows titles, times, locations, and descriptions. **See only free/busy** hides everything but the time slots — pick **See all event details** so the block has something useful to display.

## Part 2 — Copy the public .ics address

1. Still in **Settings and sharing** for that calendar, scroll down to the **Integrate calendar** section.
2. Find the field labeled **Public address in iCal format**. It ends in `.ics` and looks something like:

```text
https://calendar.google.com/calendar/ical/your-calendar-id/public/basic.ics
```

1. Click the copy button next to it (or select the whole address and copy it).

That copied address is your feed URL.

> [!NOTE]
> Google's own step-by-step help for this is here: <https://support.google.com/calendar/answer/37083>

## Part 3 — Paste the URL into the block

1. Edit the WordPress page or post where you want the events to appear.
2. Add the **Calendar Feed** block (search for "Calendar Feed" in the block inserter).
3. Open the block's settings panel on the right (the **Calendar Settings** panel). If you don't see it, click the gear/settings icon in the top-right of the editor with the block selected.
4. Paste the copied `.ics` address into the **Feed URL** field.
5. A preview of the upcoming events should appear after a moment.

You can then adjust:

- **Number of Events** — how many upcoming events to show (1–20).
- **Layout Style** — **List** or **Grid**.

Save or update the page, and the events will show on the live site.

---

## Troubleshooting

**The preview says "No upcoming events found."**
The feed loaded correctly, but it has no events dated today or later. Past events are not shown. Add a future event to the calendar, or double-check you copied the right calendar's address.

**The preview shows an error or won't load.**

- Make sure the calendar is set to **public** (Part 1). A private calendar's `.ics` address will not work here.
- Make sure you copied the **Public address in iCal format** (it ends in `.ics`), not the "Secret address" or the regular sharing link.
- Confirm the address was pasted in full, with no spaces before or after it.

**I changed the calendar but the block still shows old events.**
The block caches the feed for a short time so pages load quickly, so changes can take a little while to appear. To see updates immediately, select the block and click the **Clear Cache** button (in the block toolbar or the settings panel).

**Can I use a calendar from something other than Google?**
Yes. Any service that gives you a public `.ics` / iCalendar feed URL will work — Outlook, Apple iCloud Calendar, Eventbrite, and many others offer one. Look in that service's sharing or "subscribe" settings for an address ending in `.ics` and paste it into the **Feed URL** field the same way.
