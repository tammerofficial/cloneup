import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatTimeFromTimestamp(timestamp: Date) {
    const date = new Date(timestamp);

    // Extract hours and minutes
    const hours = date.getHours();
    const minutes = date.getMinutes();

    // Format the time to always show two digits (e.g., "09" for minutes or hours less than 10)
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
}

export function formatTimestamp(timestamp: Date): string {
    const date = new Date(timestamp);
    const now = new Date();

    // Convert both to timestamps (number)
    const nowTimestamp: number = now.getTime();
    const dateTimestamp: number = date.getTime();

    // Check if the timestamp is from today
    if (date.toDateString() === now.toDateString()) {
        // If today, return just the time (HH:mm)
        const hours = date.getHours();
        const minutes = date.getMinutes();
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    }

    // Check if the timestamp is within the last 4 days
    const daysDifference = Math.floor((nowTimestamp - dateTimestamp) / (1000 * 3600 * 24));
    if (daysDifference <= 4) {
        // If within 4 days, return the day of the week
        return date.toLocaleDateString('en-US', { weekday: 'long' });
    }

    // If older than 4 days, return the full date (YYYY-MM-DD)
    const fullDate = date.toISOString().split('T')[0]; // Extract just the date part
    return fullDate;
}
